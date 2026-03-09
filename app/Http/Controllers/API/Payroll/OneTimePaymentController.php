<?php

namespace App\Http\Controllers\API\Payroll;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Events\UserloginNotification;
use App\Http\Controllers\Controller;
use App\Models\AdjustementType;
use App\Models\ApprovalsAndRequest;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\CustomField;
use App\Models\CustomFieldHistory;
use App\Models\Notification;
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
use App\Models\PositionPayFrequency;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\RequestApprovelByPid;
use App\Models\SalesMaster;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionLock;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Models\UserSchedule;
use App\Models\UserScheduleDetail;
use App\Models\UserWagesHistory;
use App\Traits\EmailNotificationTrait;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Validator;

class OneTimePaymentController extends Controller
{
    use EmailNotificationTrait;
    use EvereeTrait;
    use PayFrequencyTrait;

    public function create_request_payment(Request $request): JsonResponse
    {
        if (! empty($request->request_id)) {
            $apprequest = ApprovalsAndRequest::where('id', $request->request_id)->where('status', 'Approved')->first();
            if (! empty($apprequest)) {
                $res = $this->create_payment($request, $apprequest);
            }
        }

        return response()->json([
            'ApiName' => 'one_time_payment_request',
            'status' => true,
            'message' => 'success!',
        ], 200);
    }

    public function create_payment(Request $request): JsonResponse
    {
        $req_user_id = '';
        $type_id = '';
        $req_id = '';
        $req_amount = '';
        $req_no = '';
        $req_des = '';

        try {
            $Validator = Validator::make($request->all(), [
                'user_id' => 'required',
                'amount' => ['required', 'numeric', function ($attribute, $value, $fail) {
                    if ($value <= 0) {
                        $fail('The :attribute must be a positive number.');
                    }
                }],
                'adjustment_type_id' => 'required',
                // 'req_id' => 'required',
            ]);

            if ($Validator->fails()) {
                return response()->json([
                    'error' => $Validator->errors(),
                ], 400);
            }

            if ($request->adjustment_type_id == 5) {
                return response()->json([
                    'error' => ['adjustment_type_id' => ['Invalid adjustment type '.$request->adjustment_type_id]],
                ], 400);
            }

            $userData = User::find($request->user_id);
            if ($userData) {
                if ($userData->stop_payroll) {
                    return response()->json([
                        'error' => ['mmessege' => [$userData?->first_name.' '.$userData?->last_name.' payroll have been stopped, therefore a one-time payment can\'t be made.']],
                    ], 400);
                }
            }

            $unclearPayroll = Payroll::where('user_id', $request->user_id)->whereIn('finalize_status', [1, 2])->first();

            if ($unclearPayroll) {
                return response()->json([
                    'error' => ['mmessege' => ['At this time, we are unable to process your request. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.']],
                ], 400);
            }

            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            // $CrmSetting = CrmSetting::where('crm_id', 3)->first();

            if (empty($CrmData)) {
                return response()->json([
                    'error' => ['messege' => ['You are presently not set up to utilize Sequifi\'s payment services. Therefore, this payment cannot be processed. Please reach out to your system administrator.']],
                ], 400);
            } else {
                $req_data = null;
                if (! empty($request->req_id)) {
                    $req_data = ApprovalsAndRequest::where('id', $request->req_id)->where('status', 'Approved')->first();
                    if ($req_data) {
                        // CRITICAL: Ensure user_id matches the approved request
                        if (isset($request->user_id) && $request->user_id != $req_data->user_id) {
                            return response()->json([
                                'error' => ['message' => ['Cannot create payment for user ID '.$request->user_id.'. This approved request belongs to user ID '.$req_data->user_id.'.']],
                            ], 400);
                        }

                        // DUPLICATE PREVENTION: Check if payment already exists for this request
                        $existingPayment = OneTimePayments::where('req_id', $request->req_id)
                            ->where('user_id', $req_data->user_id)
                            ->whereIn('everee_payment_status', [0, 1]) // Pending or Approved
                            ->first();

                        if ($existingPayment) {
                            return response()->json([
                                'error' => ['message' => ['Payment already exists for this request (Transaction ID: '.($existingPayment->everee_paymentId ?? $existingPayment->req_no).'). Please check the payment history.']],
                            ], 409); // 409 Conflict status code
                        }
                    }
                }

                // RACE CONDITION FIX: Prevent duplicate payments from rapid button clicks
                // Check for recent duplicates (same user + amount + type within 60 seconds) with database lock
                $cutoffTime = now()->subSeconds(60);
                $recentDuplicate = OneTimePayments::where('user_id', $request->user_id)
                    ->where('adjustment_type_id', $request->adjustment_type_id)
                    ->where('amount', $request->amount)
                    ->where('created_at', '>=', $cutoffTime)
                    ->whereIn('everee_payment_status', [0, 1])
                    ->lockForUpdate()
                    ->first();

                if ($recentDuplicate) {
                    return response()->json([
                        'status' => false,
                        'ApiName' => 'one_time_payment',
                        'message' => 'Duplicate payment detected',
                        'error' => ['message' => ['A payment with the same amount ($'.$request->amount.') was created '.$recentDuplicate->created_at->diffForHumans().' (Transaction: '.$recentDuplicate->req_no.'). Please check payment history.']],
                    ], 409);
                }

                if ($req_data || $req_id == '') {
                    $req_user_id = isset($req_data->user_id) ? $req_data->user_id : $request->user_id;
                    $type_id = isset($req_data->adjustment_type_id) ? $req_data->adjustment_type_id : $request->adjustment_type_id;
                    $req_id = isset($req_data->id) ? $req_data->id : null;
                    $req_amount = isset($req_data->amount) ? $req_data->amount : $request->amount;
                    $req_no = isset($req_data->req_no) ? $req_data->req_no : null;
                    $req_des = isset($req_data->description) ? $req_data->description : null;
                    $domainName = config('app.domain_name');
                    $uid = isset($request->user_id) ? $request->user_id : $req_user_id;
                    $user = User::where('id', $uid)->first();
                    $positionPayFrequency = PositionPayFrequency::query()->where(['position_id' => $user->sub_position_id])->first();
                    if (! $positionPayFrequency) {
                        return response()->json([
                            'error' => ['messege' => ['sorry user doesn\'t have any position pay frequency that\'s why we are unable to process right now.']],
                        ], 400);
                    }

                    $check = OneTimePayments::where('adjustment_type_id', $type_id)->count();
                    $CrmData = Crms::where('id', 3)->where('status', 1)->first();
                    // $CrmSetting = CrmSetting::where('crm_id', 3)->first();

                    if ($user && ($user->employee_id == null || $user->employee_id == '' || $user->everee_workerId == null || $user->everee_workerId == '')) {
                        return response()->json([
                            'ApiName' => 'one_time_payment',
                            'status' => false,
                            'message' => 'Since the user has not completed their self-onboarding process and their information is incomplete, we are unable to process the payment. Please ensure their details are fully updated to proceed with the payment.',
                        ], 400);
                    }

                    if (! empty($CrmData)) {
                        if ($type_id == 1) {
                            if (! empty($check)) {
                                $req_no = 'OTPD'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTPD'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 2) {
                            if (! empty($check)) {
                                $req_no = 'OTR'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 3) {
                            if (! empty($check)) {
                                $req_no = 'OTB'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTB'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 4) {
                            if (! empty($check)) {
                                $req_no = 'OTA'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 6) {
                            if (! empty($check)) {
                                $req_no = 'OTI'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTI'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 10) {
                            if (! empty($request->customer_pid)) {
                                $req_no = 'OTC'.$request->customer_pid;
                            } else {
                                $req_no = 'OTC'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 11) {
                            if (! empty($check)) {
                                $req_no = 'OTOV'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTOV'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } else {
                            if (! empty($check)) {
                                $req_no = 'OTO'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTO'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        }

                        $external_id = $user->employee_id.'-'.strtotime('now');
                        $amount = isset($request->amount) ? $request->amount : $req_amount;
                        $evereeFields = [
                            'usersdata' => [
                                'employee_id' => $user->employee_id,
                                'everee_workerId' => $user->everee_workerId,
                                'id' => $user->id,
                                'worker_type' => $user->worker_type,
                                'onboardProcess' => $user->onboardProcess,
                            ],
                            'net_pay' => $amount,
                            'payable_type' => 'one time payment',
                            'payable_label' => 'one time payment',
                        ];

                        if ($type_id == 2) {
                            $payable = $this->add_payable($evereeFields, $external_id, 'REIMBURSEMENT');
                        } elseif ($type_id == 3 || $type_id == 6) {
                            $payable = $this->add_payable($evereeFields, $external_id, 'BONUS');
                        } else {
                            $payable = $this->add_payable($evereeFields, $external_id, 'COMMISSION');
                        }

                        if ((isset($payable['success']['status']) && $payable['success']['status'] == true)) {
                            $onetimePayment = 1;
                            $payable_request = $this->payable_request($evereeFields, $onetimePayment);
                            $date = date('Y-m-d');
                            if ($req_id != '') {
                                $req_data->status = 'Accept';
                                $req_data->payroll_id = 0;
                                $req_data->pay_period_from = $date;
                                $req_data->pay_period_to = $date;
                                $req_data->save();
                            }

                            create_paystub_employee([
                                'user_id' => $uid,
                                'pay_period_from' => date('Y-m-d'),
                                'pay_period_to' => date('Y-m-d'),
                            ]);

                            $response = OneTimePayments::create([
                                'user_id' => $uid,
                                'req_id' => $req_id ? $req_id : null,
                                'pay_by' => Auth::user()->id,
                                'req_no' => $req_no ? $req_no : null,
                                'everee_external_id' => $external_id,
                                'everee_payment_req_id' => isset($payable_request['success']['paymentId']) ? $payable_request['success']['paymentId'] : null,
                                'everee_paymentId' => isset($payable_request['success']['everee_payment_id']) ? $payable_request['success']['everee_payment_id'] : null,
                                'adjustment_type_id' => $type_id,
                                'amount' => $amount,
                                'description' => $request->description ? $request->description : $req_des,
                                'pay_date' => date('Y-m-d'),
                                'payment_status' => 3,
                                'everee_status' => 1,
                                'everee_json_response' => isset($payable_request) ? json_encode($payable_request) : null,
                                'everee_webhook_response' => null,
                                'everee_payment_status' => 0,
                            ]);
                            $attributes = $request->all();

                            // Merge additional keys into the attributes array
                            $additionalProperties = [
                                'req_no' => $response->req_no, // Include request number
                                'everee_paymentId' => $response->everee_paymentId,
                                'payment_status' => $response->payment_status,
                                'everee_payment_status' => $response->everee_payment_status,
                            ];

                            $mergedProperties = array_merge($attributes, $additionalProperties);

                            // Log activity
                            activity()
                                ->causedBy(Auth::user()) // The user who triggered the action
                                ->performedOn($response) // The OneTimePayments record
                                ->withProperties(['attributes' => $mergedProperties])
                                ->event('created')
                                ->log('One-time payment created'); // Log description

                            return response()->json([
                                'ApiName' => 'one_time_payment',
                                'status' => true,
                                'message' => 'success!',
                                'everee_response' => $payable['success']['everee_response'],
                                'data' => $response,
                            ], 200);
                        } else {
                            $payable['fail']['everee_response']['errorMessage'] = isset($payable['fail']['everee_response']['errorMessage']) ? $payable['fail']['everee_response']['errorMessage'] : (isset($payable['fail']['everee_response']['error']) ? $payable['fail']['everee_response']['error'] : 'An error occurred during the Everee payment process.');

                            return response()->json([
                                'status' => false,
                                'message' => $payable['fail']['everee_response']['errorMessage'],
                                'ApiName' => 'one_time_payment',
                                'response' => $payable['fail']['everee_response'],
                            ], 400);
                        }
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Sorry the request you are looking for is not found.',
                    ], 400);
                }
            }

        } catch (\Exception $e) {
            // Log activity for failed payment creation
            activity()
                ->causedBy(Auth::user())
                ->withProperties(['error' => $e->getMessage()])
                ->log('Failed to create one-time payment');

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'Line' => $e->getLine(),
                'File' => $e->getFile(),
            ], 400);
        }
    }

    public function admin_create_payment(Request $request): JsonResponse
    {
        $req_user_id = '';
        $type_id = '';
        $req_id = '';
        $req_amount = '';
        $req_no = '';
        $req_des = '';

        try {
            $Validator = Validator::make($request->all(), [
                'user_id' => 'required',
                'amount' => ['required', 'numeric'],
                'adjustment_type_id' => 'required',
                // 'req_id' => 'required',
            ]);

            if ($Validator->fails()) {
                return response()->json([
                    'error' => $Validator->errors(),
                ], 400);
            }

            if ($request->adjustment_type_id == 5) {
                return response()->json([
                    'error' => ['adjustment_type_id' => ['Invalid adjustment type '.$request->adjustment_type_id]],
                ], 400);
            }

            $unclearPayroll = Payroll::where('user_id', $request->user_id)->whereIn('finalize_status', [1, 2])->first();

            if ($unclearPayroll) {
                return response()->json([
                    'error' => ['mmessege' => ['At this time, we are unable to process your request. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.']],
                ], 400);
            }

            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            // $CrmSetting = CrmSetting::where('crm_id', 3)->first();

            if (empty($CrmData)) {
                return response()->json([
                    'error' => ['messege' => ['You are presently not set up to utilize Sequifi\'s payment services. Therefore, this payment cannot be processed. Please reach out to your system administrator.']],
                ], 400);
            } else {
                $req_data = ApprovalsAndRequest::where('id', $request->req_id)->where('status', 'Approved')->first();

                // DUPLICATE PREVENTION for W2: Check if payment already exists for this request
                if ($req_data && ! empty($request->req_id)) {
                    $existingPayment = OneTimePayments::where('req_id', $request->req_id)
                        ->where('user_id', $req_data->user_id)
                        ->whereIn('everee_payment_status', [0, 1]) // Pending or Approved
                        ->first();

                    if ($existingPayment) {
                        return response()->json([
                            'error' => ['message' => ['Payment already exists for this request (Transaction ID: '.($existingPayment->everee_paymentId ?? $existingPayment->req_no).'). Please check the payment history.']],
                        ], 409); // 409 Conflict status code
                    }
                }

                // RACE CONDITION FIX for W2: Prevent duplicate payments from rapid button clicks
                // Check for recent duplicates (same user + amount + type within 60 seconds) with database lock
                $cutoffTime = now()->subSeconds(60);
                $recentDuplicate = OneTimePayments::where('user_id', $request->user_id)
                    ->where('adjustment_type_id', $request->adjustment_type_id)
                    ->where('amount', $request->amount)
                    ->where('created_at', '>=', $cutoffTime)
                    ->whereIn('everee_payment_status', [0, 1])
                    ->lockForUpdate()
                    ->first();

                if ($recentDuplicate) {
                    return response()->json([
                        'status' => false,
                        'ApiName' => 'one_time_payment',
                        'message' => 'Duplicate payment detected',
                        'error' => ['message' => ['A payment with the same amount ($'.$request->amount.') was created '.$recentDuplicate->created_at->diffForHumans().' (Transaction: '.$recentDuplicate->req_no.'). Please check payment history.']],
                    ], 409);
                }

                if ($req_data || $req_id == '') {
                    $req_user_id = isset($req_data->user_id) ? $req_data->user_id : $request->user_id;
                    $type_id = isset($req_data->adjustment_type_id) ? $req_data->adjustment_type_id : $request->adjustment_type_id;
                    $req_id = isset($req_data->id) ? $req_data->id : null;
                    $req_amount = isset($req_data->amount) ? $req_data->amount : $request->amount;
                    $req_no = isset($req_data->req_no) ? $req_data->req_no : null;
                    $req_des = isset($req_data->description) ? $req_data->description : null;

                    $uid = isset($request->user_id) ? $request->user_id : $req_user_id;
                    $user = User::where('id', $uid)->first();
                    $positionPayFrequency = PositionPayFrequency::query()->where(['position_id' => $user->sub_position_id])->first();
                    if (! $positionPayFrequency) {
                        return response()->json([
                            'error' => ['messege' => ['sorry user doesn\'t have any position pay frequency that\'s why we are unable to process right now.']],
                        ], 400);
                    }

                    $check = OneTimePayments::where('adjustment_type_id', $type_id)->count();
                    $CrmData = Crms::where('id', 3)->where('status', 1)->first();
                    // $CrmSetting = CrmSetting::where('crm_id', 3)->first();

                    if ($user && ($user->employee_id == null || $user->employee_id == '' || $user->everee_workerId == null || $user->everee_workerId == '')) {
                        return response()->json([
                            'ApiName' => 'one_time_payment',
                            'status' => false,
                            'message' => 'Since the user has not completed their self-onboarding process and their information is incomplete, we are unable to process the payment. Please ensure their details are fully updated to proceed with the payment.',
                        ], 400);
                    }

                    if (! empty($CrmData)) {
                        if ($type_id == 1) {
                            if (! empty($check)) {
                                $req_no = 'OTPD'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTPD'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 2) {
                            if (! empty($check)) {
                                $req_no = 'OTR'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 3) {
                            if (! empty($check)) {
                                $req_no = 'OTB'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTB'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 4) {
                            if (! empty($check)) {
                                $req_no = 'OTA'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 6) {
                            if (! empty($check)) {
                                $req_no = 'OTI'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTI'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 10) {
                            if (! empty($request->customer_pid)) {
                                $req_no = 'OTC'.$request->customer_pid;
                            } else {
                                $req_no = 'OTC'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 11) {
                            if (! empty($check)) {
                                $req_no = 'OTOV'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTOV'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } elseif ($type_id == 13) {
                            // If there are existing approval requests, increment the count and generate a new request number
                            if (! empty($check)) {
                                // Format: 'PA' followed by the incremented number padded to 6 digits (e.g. PA000123)
                                $req_no = 'PA'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                // If no existing requests, start from 'PA000001'
                                $req_no = 'PA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        } else {
                            if (! empty($check)) {
                                $req_no = 'OTO'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                            } else {
                                $req_no = 'OTO'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                            }
                        }

                        $external_id = $user->employee_id.'-'.strtotime('now');
                        $amount = isset($request->amount) ? $request->amount : $req_amount;
                        $evereeFields = [
                            'usersdata' => [
                                'employee_id' => $user->employee_id,
                                'everee_workerId' => $user->everee_workerId,
                                'id' => $user->id,
                                'worker_type' => $user->worker_type,
                                'onboardProcess' => $user->onboardProcess,
                            ],
                            'net_pay' => $amount,
                            'payable_type' => 'one time payment',
                            'payable_label' => 'one time payment',
                        ];

                        if ($type_id == 2) {
                            $payable = $this->add_payable($evereeFields, $external_id, 'REIMBURSEMENT');
                        } else {
                            $payable = $this->add_payable($evereeFields, $external_id, 'COMMISSION');
                        }

                        if ((isset($payable['success']['status']) && $payable['success']['status'] == true)) {
                            $onetimePayment = 1;
                            $payable_request = $this->payable_request($evereeFields, $onetimePayment);
                            $date = date('Y-m-d');
                            if ($req_id != '') {
                                $req_data->status = 'Accept';
                                $req_data->payroll_id = 0;
                                $req_data->pay_period_from = $date;
                                $req_data->pay_period_to = $date;
                                $req_data->save();
                            }

                            create_paystub_employee([
                                'user_id' => $uid,
                                'pay_period_from' => date('Y-m-d'),
                                'pay_period_to' => date('Y-m-d'),
                            ]);

                            $response = OneTimePayments::create([
                                'user_id' => $uid,
                                'req_id' => $req_id ? $req_id : null,
                                'pay_by' => Auth::user()->id,
                                'req_no' => $req_no ? $req_no : null,
                                'everee_external_id' => $external_id,
                                'everee_payment_req_id' => isset($payable_request['success']['paymentId']) ? $payable_request['success']['paymentId'] : null,
                                'everee_paymentId' => isset($payable_request['success']['everee_payment_id']) ? $payable_request['success']['everee_payment_id'] : null,
                                'adjustment_type_id' => $type_id,
                                'amount' => $amount,
                                'description' => $request->description ? $request->description : $req_des,
                                'pay_date' => date('Y-m-d'),
                                'payment_status' => 3,
                                'everee_status' => 1,
                                'everee_json_response' => isset($payable_request) ? json_encode($payable_request) : null,
                                'everee_webhook_response' => null,
                                'everee_payment_status' => 0,
                            ]);
                            $attributes = $request->all();

                            // Merge additional keys into the attributes array
                            $additionalProperties = [
                                'req_no' => $response->req_no, // Include request number
                                'everee_paymentId' => $response->everee_paymentId,
                                'payment_status' => $response->payment_status,
                                'everee_payment_status' => $response->everee_payment_status,
                            ];

                            $mergedProperties = array_merge($attributes, $additionalProperties);

                            // Log activity
                            activity()
                                ->causedBy(Auth::user()) // The user who triggered the action
                                ->performedOn($response) // The OneTimePayments record
                                ->withProperties(['attributes' => $mergedProperties])
                                ->event('created')
                                ->log('One-time payment created'); // Log description

                            return response()->json([
                                'ApiName' => 'one_time_payment',
                                'status' => true,
                                'message' => 'success!',
                                'everee_response' => $payable['success']['everee_response'],
                                'data' => $response,
                            ], 200);
                        } else {
                            $payable['fail']['everee_response']['errorMessage'] = isset($payable['fail']['everee_response']['errorMessage']) ? $payable['fail']['everee_response']['errorMessage'] : (isset($payable['fail']['everee_response']['error']) ? $payable['fail']['everee_response']['error'] : 'An error occurred during the Everee payment process.');

                            return response()->json([
                                'status' => false,
                                'message' => $payable['fail']['everee_response']['errorMessage'],
                                'ApiName' => 'one_time_payment',
                                'response' => $payable['fail']['everee_response'],
                            ], 400);
                        }
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Sorry the request you are looking for is not found.',
                    ], 400);
                }
            }

        } catch (\Exception $e) {
            // Log activity for failed payment creation
            activity()
                ->causedBy(Auth::user())
                ->withProperties(['error' => $e->getMessage()])
                ->log('Failed to create one-time payment');

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'Line' => $e->getLine(),
                'File' => $e->getFile(),
            ], 400);
        }
    }

    public function addPaymentAdminRequest(Request $request)
    {
        if ($request->adjustment_type_id == 10) {
            $Validator = Validator::make($request->all(), ['document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:2048', 'customer_pid' => 'required']);
        } else {
            $Validator = Validator::make(
                $request->all(),
                [
                    'document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:2048',
                    // 'logo'  => 'required|mimes:jpg,png,jpeg,gif,svg|max:2048',
                ]
            );
        }
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        if (Auth::user()->is_super_admin == 1) {
            if (! $request->image == null) {
                $file = $request->file('image');
                if (isset($file) && $file != null && $file != '') {
                    // s3 bucket
                    $img_path = time().$file->getClientOriginalName();
                    $img_path = str_replace(' ', '_', $img_path);
                    $awsPath = config('app.domain_name').'/'.'request-image/'.$img_path;
                    s3_upload($awsPath, file_get_contents($file), false);
                    // s3 bucket end
                }
                $image_path = time().$file->getClientOriginalName();
                $ex = $file->getClientOriginalExtension();
                $destinationPath = 'request-image';
                $image_path = $file->move($destinationPath, $img_path);
            } else {
                $image_path = '';
            }

            $user_id = Auth::user()->id;
            $user_data = User::where('id', $user_id)->first();
            if (! empty($request->user_id)) {
                $userID = $request->user_id;
                $userManager = $user_data = User::where('id', $userID)->first();
                $managerId = $userManager->manager_id;

            } else {
                $userID = $user_id;
            }

            $effectiveDate = date('Y-m-d');
            if ($request->adjustment_type_id == 2) {
                $effectiveDate = $request->cost_date;
            } elseif ($request->adjustment_type_id == 3 || $request->adjustment_type_id == 5 || $request->adjustment_type_id == 6) {
                $effectiveDate = $request->request_date;
            } elseif ($request->adjustment_type_id == 7 || $request->adjustment_type_id == 8) {
                $effectiveDate = $request->end_date;
            } elseif ($request->adjustment_type_id == 9) {
                $effectiveDate = $request->adjustment_date;
            }

            $terminated = checkTerminateFlag($userID, $effectiveDate);
            if ($terminated && $terminated->is_terminate) {
                return response()->json([
                    'ApiName' => 'add-request',
                    'status' => false,
                    'message' => $user_data?->first_name.' '.$user_data?->last_name.' have been terminated, therefore a one-time payment can\'t be made.',
                ], 400);
            }

            $dismissed = checkDismissFlag($userID, $effectiveDate);
            if ($dismissed && $dismissed->dismiss) {
                return response()->json([
                    'ApiName' => 'add-request',
                    'status' => false,
                    'message' => $user_data?->first_name.' '.$user_data?->last_name.' have been disabled, therefore a one-time payment can\'t be made.',
                ], 400);
            }

            if ($user_data?->disable_login) {
                return response()->json([
                    'ApiName' => 'add-request',
                    'status' => false,
                    'message' => $user_data?->first_name.' '.$user_data?->last_name.' have been suspended, therefore a one-time payment can\'t be made.',
                ], 400);
            }
            // echo $data->name;die;
            $adjustementType = AdjustementType::where('id', $request->adjustment_type_id)->first();

            $approvalsAndRequest = ApprovalsAndRequest::where('adjustment_type_id', $adjustementType->id)->whereNotNull('req_no')->latest('id')->first();
            if ($approvalsAndRequest) {
                $approvalsAndRequest = preg_replace('/[A-Za-z]+/', '', $approvalsAndRequest->req_no);
            }
            if ($adjustementType->id == 1) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'PD'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'PD'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 2) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'R'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'R'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 3) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'B'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'B'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 4) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'A'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'A'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 5) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'FF'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'FF'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 6) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'I'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'I'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 7) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'L'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'L'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 8) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'PT'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'PT'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 9) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'TA'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'TA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 10) {
                if (! empty($request->customer_pid)) {
                    $req_no = 'C'.$request->customer_pid;
                } else {
                    $req_no = 'C'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 11) {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OV'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OV'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 13) {
                // If there are existing approval requests, increment the count and generate a new request number
                if (! empty($approvalsAndRequest)) {
                    // Format: 'PA' followed by the incremented number padded to 6 digits (e.g. PA000123)
                    $req_no = 'PA'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    // If no existing requests, start from 'PA000001'
                    $req_no = 'PA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } else {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'O'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'O'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            }
            // echo $req_no;die;
            // return $request;

            if ($adjustementType->id == 7 || $adjustementType->id == 8 || $adjustementType->id == 9) {
                $startDate = $request->start_date;
                $endDate = $request->end_date;

                $insertUpdate = [
                    'user_id' => $userID,
                    'manager_id' => isset($managerId) ? $managerId : $user_data->manager_id,
                    'created_by' => $user_id,
                    'req_no' => $req_no,
                    'approved_by' => $user_id,
                    'adjustment_type_id' => $request->adjustment_type_id,
                    'pay_period' => $request->pay_period,
                    'state_id' => $request->state_id,
                    'dispute_type' => $request->dispute_type,
                    'description' => $request->description,
                    'cost_tracking_id' => $request->cost_tracking_id,
                    'emi' => $request->emi,
                    'request_date' => $request->request_date,
                    'cost_date' => $request->cost_date,
                    'amount' => $request->amount,
                    'image' => $image_path,
                    'status' => 'Approved',
                    'start_date' => isset($request->start_date) ? $request->start_date : null,
                    'end_date' => isset($request->end_date) ? $request->end_date : null,
                    'pto_hours_perday' => isset($request->pto_hours_perday) ? $request->pto_hours_perday : null,
                    'adjustment_date' => isset($request->adjustment_date) ? $request->adjustment_date : null,
                    'clock_in' => isset($request->clock_in) ? $request->clock_in : null,
                    'clock_out' => isset($request->clock_out) ? $request->clock_out : null,
                    'lunch_adjustment' => isset($request->lunch_adjustment) ? $request->lunch_adjustment : null,
                    'break_adjustment' => isset($request->break_adjustment) ? $request->break_adjustment : null,
                ];

                if ($adjustementType->id == 9) {
                    $adjustmentDate = $request->adjustment_date;
                    $clock_in = null;
                    $clock_out = null;
                    $userPosition = User::select('sub_position_id', 'office_id')->where('id', $request->user_id)->first();
                    $office_id = $userPosition->office_id;
                    if (isset($request->clock_in) && ! empty($request->clock_in)) {
                        $clock_in = $request->clock_in;
                    }
                    if (isset($request->clock_out) && ! empty($request->clock_out)) {
                        $clock_out = $request->clock_out;
                    }
                    $this->createOrUpdateUserSchedules($request->user_id, $office_id, $clock_in, $clock_out, $adjustmentDate, $request->lunch_adjustment);
                    $leaveData = ApprovalsAndRequest::where(['user_id' => $userID, 'adjustment_type_id' => 7])->where('start_date', '<=', $adjustmentDate)->where('end_date', '>=', $adjustmentDate)->where('status', 'Approved')->first();
                    if ($leaveData) {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because this adjustment date has already been leave request'], 400);
                    } else {
                        $approvalData = ApprovalsAndRequest::where('adjustment_type_id', $adjustementType->id)->where(['user_id' => $userID, 'adjustment_date' => $adjustmentDate])->first();
                        if ($approvalData) {
                            $insertUpdate['req_no'] = $approvalData->req_no;
                            ApprovalsAndRequest::where('id', $approvalData->id)->update($insertUpdate);
                        } else {
                            ApprovalsAndRequest::create($insertUpdate);
                        }
                    }
                } elseif ($adjustementType->id == 7) {
                    $userPosition = User::select('sub_position_id', 'office_id')->where('id', $request->user_id)->first();
                    $subPositionId = $userPosition->sub_position_id;
                    $spayFrequency = $this->payFrequency($startDate, $subPositionId);
                    $epayFrequency = $this->payFrequency($endDate, $subPositionId);

                    $office_id = $userPosition->office_id;
                    $schedule_start_date = Carbon::parse($request->start_date);
                    $schedule_end_date = Carbon::parse($request->end_date);
                    $schedule_from = '08:00:00';
                    $schedule_to = '16:00:00';
                    for ($date = $schedule_start_date; $date->lte($schedule_end_date); $date->addDay()) {
                        $clock_in = $date->copy()->setTimeFromTimeString($schedule_from);
                        $clock_out = $date->copy()->setTimeFromTimeString($schedule_to);
                        // dd($schedule_from, $schedule_to, $clock_in, $clock_out);
                        $this->createOrUpdateUserSchedules($request->user_id, $office_id, $clock_in, $clock_out, $date, null);
                    }

                    if ((! empty($spayFrequency) && $spayFrequency->closed_status == 1) || (! empty($spayFrequency) && $epayFrequency->closed_status == 1)) {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the pay period has already been closed'], 400);

                    } else {
                        $approvalData = ApprovalsAndRequest::where(['adjustment_type_id' => $adjustementType->id, 'user_id' => $userID])->where(['start_date' => $startDate, 'end_date' => $endDate])->first();
                        if ($approvalData) {
                            $insertUpdate['req_no'] = $approvalData->req_no;
                            ApprovalsAndRequest::where('id', $approvalData->id)->update($insertUpdate);
                        } else {
                            ApprovalsAndRequest::create($insertUpdate);
                        }
                    }
                } elseif ($adjustementType->id == 8) {
                    $userPosition = User::select('sub_position_id', 'office_id')->where('id', $request->user_id)->first();
                    $subPositionId = $userPosition->sub_position_id;
                    $spayFrequency = $this->payFrequency($startDate, $subPositionId);
                    $epayFrequency = $this->payFrequency($endDate, $subPositionId);

                    $office_id = $userPosition->office_id;
                    $schedule_start_date = Carbon::parse($request->start_date);
                    $schedule_end_date = Carbon::parse($request->end_date);
                    $pto_hours_perday = $request->pto_hours_perday;
                    $schedule_from = '08:00:00';
                    $sc_time = Carbon::createFromFormat('H:i:s', $schedule_from);
                    $schedule_to = $sc_time->addHours($pto_hours_perday)->format('H:i:s');
                    // dd($schedule_to);
                    for ($date = $schedule_start_date; $date->lte($schedule_end_date); $date->addDay()) {
                        $clock_in = $date->copy()->setTimeFromTimeString($schedule_from);
                        $clock_out = $date->copy()->setTimeFromTimeString($schedule_to);
                        // dd($schedule_from, $schedule_to, $clock_in, $clock_out);
                        $this->createOrUpdateUserSchedules($request->user_id, $office_id, $clock_in, $clock_out, $date, null);
                    }
                    if ((! empty($spayFrequency) && $spayFrequency->closed_status == 1) || (! empty($spayFrequency) && $epayFrequency->closed_status == 1)) {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the pay period has already been closed'], 400);

                    } else {
                        $start = Carbon::parse($startDate);
                        $end = Carbon::parse($endDate);
                        $daysCount = $start->diffInDays($end) + 1;
                        $ptoHoursPerday = ($request->pto_hours_perday * $daysCount);
                        $date = date('Y-m-d');
                        $calpto = $this->calculatePTOs($userID);
                        $usedpto = isset($calpto['total_user_ptos']) ? $calpto['total_user_ptos'] : 0;
                        // $userWagesHistory = UserWagesHistory::where('user_id', $userID)->where('pto_hours_effective_date', '<=', $date)->orderBy('pto_hours_effective_date', 'DESC')->first();
                        // print_r($userWagesHistory);die();
                        if ($calpto && ! empty($calpto['total_ptos']) && ($usedpto + $ptoHoursPerday) <= $calpto['total_ptos']) {
                            $checkstatus = $this->checkUsedDay($userID, $startDate, $endDate, $insertUpdate['pto_hours_perday']);
                            if (! empty($checkstatus)) {
                                return response()->json(['status' => false, 'message' => $checkstatus[0]], 400);
                            }
                            ApprovalsAndRequest::create($insertUpdate);
                        } else {
                            return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the PTO hour greater than PTO balance'], 400);
                        }
                    }
                }

            } else {

                $data = ApprovalsAndRequest::create([
                    'user_id' => $userID,
                    'manager_id' => isset($managerId) ? $managerId : $user_data->manager_id,
                    'created_by' => $user_id,
                    'req_no' => $req_no,
                    'approved_by' => $user_id,
                    'adjustment_type_id' => $request->adjustment_type_id,
                    'pay_period' => $request->pay_period,
                    'state_id' => $request->state_id,
                    'dispute_type' => $request->dispute_type,
                    // 'customer_pid' => $request->customer_pid,
                    'description' => $request->description,
                    'cost_tracking_id' => $request->cost_tracking_id,
                    'emi' => $request->emi,
                    'request_date' => $request->request_date,
                    'cost_date' => $request->cost_date,
                    'amount' => $request->amount,
                    'image' => $image_path,
                    'status' => 'Approved',
                    'start_date' => isset($request->start_date) ? $request->start_date : null,
                    'end_date' => isset($request->end_date) ? $request->end_date : null,
                    'pto_hours_perday' => isset($request->pto_hours_perday) ? $request->pto_hours_perday : null,
                    'adjustment_date' => isset($request->adjustment_date) ? $request->adjustment_date : null,
                    'clock_in' => isset($request->clock_in) ? $request->clock_in : null,
                    'clock_out' => isset($request->clock_out) ? $request->clock_out : null,
                    'lunch_adjustment' => isset($request->lunch_adjustment) ? $request->lunch_adjustment : null,
                    'break_adjustment' => isset($request->break_adjustment) ? $request->break_adjustment : null,
                ]);

            }

            $customerPid = $request->customer_pid;
            if ($customerPid) {
                //  $pid = implode(',',$customerPid);
                $valPid = explode(',', $customerPid);
                foreach ($valPid as $val) {
                    $customerName = SalesMaster::where('pid', $val)->first();
                    RequestApprovelByPid::create([
                        'request_id' => $data->id,
                        'pid' => $val,
                        'customer_name' => isset($customerName->customer_name) ? $customerName->customer_name : null,
                    ]);
                }
            }

            if ($user_data->manager_id) {

                // $data =  Notification::create([
                //     'user_id' => isset($user_data->manager_id)?$user_data->manager_id:1,
                //     'type' => 'request-approval',
                //     'description' => 'A new request is generated by '.$user_data->first_name,
                //     'is_read' => 0,

                // ]);

                $notificationData = [
                    'user_id' => isset($user_data->manager_id) ? $user_data->manager_id : 1,
                    'device_token' => $user_data->device_token,
                    'title' => 'A new request is generated.',
                    'sound' => 'sound',
                    'type' => 'request-approval',
                    'body' => 'A new request is generated by '.$user_data->forst_name,
                ];
                $this->sendNotification($notificationData);
            }
            $user = [

                'user_id' => isset($user_data->manager_id) ? $user_data->manager_id : 1,
                'description' => 'A new request is generated by '.$user_data->first_name.' '.$user_data->last_name,
                'type' => 'request-approval',
                'is_read' => 0,
            ];
            $notify = event(new UserloginNotification($user));

            return ['message' => 'Request Completed', 'data' => $data];
        } else {
            return response()->json(['status' => false, 'message' => 'Sorry you dont have right.'], 400);
        }
    }

    public function adminPaymentRequestPayNow(Request $request)
    {
        $adminPaymentRequest = $this->addPaymentAdminRequest($request);
        if (is_array($adminPaymentRequest) && $adminPaymentRequest['message'] == 'Request Completed') {
            $req_id = $adminPaymentRequest['data']->id ? $adminPaymentRequest['data']->id : null;
            $request->merge(['req_id' => $req_id]);

            return $this->admin_create_payment($request);
        } else {
            return $adminPaymentRequest;
        }
    }

    private function calculatePTOs($user_id = null, $date = null)
    {
        if ($date == null) {
            $date = date('Y-m-d');
        }
        if ($user_id == null) {
            $user_id = Auth::user()->id;
        }
        // $user = User::find($user_id);
        $total_used_pto_hours = 0;
        $total_pto_hours = 0;
        $date = Carbon::parse($date);
        $user = UserWagesHistory::where('user_id', $user_id)->where('pto_hours_effective_date', '<=', $date)->orderBy('pto_hours_effective_date', 'DESC')->first();
        if ($user->unused_pto_expires == 'Monthly' || $user->unused_pto_expires == 'Expires Monthly') {
            $total_pto_hours = $user->pto_hours;
            $start_date = $date->copy()->startOfMonth()->toDateString();
            $end_date = $date->copy()->endOfMonth()->toDateString();
            $user_ptos = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->where(function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('start_date', [$start_date, $end_date])
                        ->orWhereBetween('end_date', [$start_date, $end_date]);
                })
                ->orderBy('start_date', 'ASC')->get(['start_date', 'end_date', 'pto_hours_perday']);

            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                if ($pto_end_date->lt($start_date) || $pto_start_date->gt($end_date)) {
                    continue; // Skip PTOs outside the current month
                }

                $overlap_start = $pto_start_date->gt($start_date) ? $pto_start_date : $start_date;
                $overlap_end = $pto_end_date->lt($end_date) ? $pto_end_date : $end_date;
                $days = $overlap_start->diffInDays($overlap_end) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        } elseif ($user->unused_pto_expires == 'Annually' || $user->unused_pto_expires == 'Expires Annually') {
            $start_date = $date->copy()->startOfYear()->toDateString();
            $end_date = $date->copy()->endOfYear()->toDateString();

            $pto_start_date = Carbon::parse($user->created_at)->lt($date->copy()->startOfYear()) ? $date->copy()->startOfYear() : Carbon::parse($user->created_at);
            $monthCount = $pto_start_date->diffInMonths($date);
            $total_pto_hours = $user->pto_hours * ($monthCount + 1);
            $user_ptos = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->where(function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('start_date', [$start_date, $end_date])
                        ->orWhereBetween('end_date', [$start_date, $end_date]);
                })
                ->orderBy('start_date', 'ASC')->get(['start_date', 'end_date', 'pto_hours_perday']);
            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                if ($pto_end_date->lt($start_date) || $pto_start_date->gt($end_date)) {
                    continue; // Skip PTOs outside the current month
                }
                $overlap_start = $pto_start_date->gt($start_date) ? $pto_start_date : $start_date;
                $overlap_end = $pto_end_date->lt($end_date) ? $pto_end_date : $end_date;
                $days = $overlap_start->diffInDays($overlap_end) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        } elseif ($user->unused_pto_expires == 'Accrues Continuously' || $user->unused_pto_expires == 'Expires Accrues Continuously') {
            $monthCount = Carbon::parse($user->created_at)->diffInMonths($date);
            $total_pto_hours = $user->pto_hours * ($monthCount + 1);
            $user_ptos = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->get(['start_date', 'end_date', 'pto_hours_perday']);

            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                $days = $pto_start_date->diffInDays($pto_end_date) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        }

        return [
            'total_ptos' => (int) $total_pto_hours,
            'total_user_ptos' => (int) $total_used_pto_hours,
            'total_remaining_ptos' => (int) $total_pto_hours - $total_used_pto_hours,
        ];
    }

    private function checkUsedDay($id, $start_date, $end_date, $requestday)
    {
        $start = \Carbon\Carbon::parse($start_date);
        $end = \Carbon\Carbon::parse($end_date);
        $error = [];
        if ($start->isSameDay($end)) {
            $approvalData = ApprovalsAndRequest::where([
                'adjustment_type_id' => 8,
                'user_id' => $id,
            ])->whereDate('start_date', '<=', $start_date)
                ->whereDate('end_date', '>=', $start_date)
                ->where('status', '!=', 'Declined')->get();
            $tpto = 0;
            foreach ($approvalData as $approval) {
                $tpto += $approval->pto_hours_perday;
            }
            if ($approvalData && ($tpto + $requestday) > 8) {
                $error[] = $start->format('m/d/y').' request cannot be created because the PTO hours exceed 8.';
            }
        } else {
            foreach ($start->daysUntil($end) as $date) {
                $approvalData = ApprovalsAndRequest::where([
                    'adjustment_type_id' => 8,
                    'user_id' => $id,
                ])->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date)
                    ->where('status', '!=', 'Declined')
                    ->get();
                $tpto = 0;
                foreach ($approvalData as $approval) {
                    $tpto += $approval->pto_hours_perday;
                }
                if ($approvalData && ($tpto + $requestday) > 8) {
                    $error[] = $date->format('m/d/y').' request cannot be created because the PTO hours exceed 8.';
                }
            }
        }

        return $error;
    }

    private function createOrUpdateUserSchedules($user_id, $office_id, $clock_in, $clock_out, $adjustment_date, $lunch)
    {
        // dd($user_id, $office_id,$clock_in,$clock_out,$adjustment_date,$lunch);
        if (! empty($lunch) && ! is_null($lunch) && $lunch != 'None') {
            $lunch = $lunch.' Mins';
        }
        $userschedule = UserSchedule::where('user_id', $user_id)->first();
        // dd($userschedule);
        $s_date = Carbon::parse($clock_in);
        $dayNumber = $s_date->dayOfWeekIso;
        if ($userschedule) {
            $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id', $userschedule->id)->where('office_id', $office_id)->wheredate('schedule_from', $adjustment_date)->first();
            if (empty($checkUserScheduleDetail) || $checkUserScheduleDetail == null) {
                $scheduleDetaisData = [
                    'schedule_id' => $userschedule->id,
                    'office_id' => $office_id,
                    'schedule_from' => $clock_in,
                    // 'schedule_to' => $scheduleTo->toDateTimeString(),
                    'schedule_to' => $clock_out,
                    'lunch_duration' => $lunch,
                    'work_days' => $dayNumber,
                    'repeated_batch' => 0,
                    'user_attendance_id' => null,
                ];
                $dataStored = UserScheduleDetail::create($scheduleDetaisData);
            } else {
                // update schedules
                // $checkUserScheduleDetail->where('schedule_id',$userschedule->id)->where('office_id',$office_id)->wheredate('schedule_from',$adjustment_date)->update(['schedule_from' => $clock_in, 'schedule_to' => $clock_out,'lunch_duration' => $lunch, 'work_days' => $dayNumber]);
            }
        } else {
            $create_userschedule = UserSchedule::create(['user_id' => $user_id, 'scheduled_by' => Auth::user()->id]);
            if ($create_userschedule) {
                $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id', $create_userschedule->id)
                    ->where('office_id', $office_id)
                    ->wheredate('schedule_from', $adjustment_date)
                    ->first();
                if (empty($checkUserScheduleDetail) || $checkUserScheduleDetail == null) {
                    $scheduleDetaisData = [
                        'schedule_id' => $create_userschedule->id,
                        'office_id' => $office_id,
                        'schedule_from' => $clock_in,
                        'schedule_to' => $clock_out,
                        'lunch_duration' => null,
                        'work_days' => $dayNumber,
                        'repeated_batch' => 0,
                        'user_attendance_id' => null,
                    ];
                    $dataStored = UserScheduleDetail::create($scheduleDetaisData);
                }
            }
        }
    }

    public function add_payable_one_time($user, $amount, $external_id, $adjustementType)
    {
        $token = $this->gettoken();
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $untrackIds = [];
        if ($adjustementType->id == 2) {
            $earningType = 'REIMBURSEMENT';
        } else {
            $earningType = 'COMMISSION';
        }
        if (! empty($user->employee_id) && ! empty($user->everee_workerId)) {
            $fields = json_encode([
                'earningAmount' => [
                    'amount' => round($amount, 2),
                    'currency' => 'USD',
                ],
                'externalId' => $external_id,
                'externalWorkerId' => $user->employee_id,
                'type' => 'one time payable',
                'label' => 'one time payable',
                'verified' => true,
                'payableModel' => 'PRE_CALCULATED',
                'earningType' => $earningType,
                'earningTimestamp' => time(),
            ]);
            $url = 'https://api-prod.everee.com/api/v2/payables';
            $method = 'POST';
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'accept: application/json',
                'content-type: application/json',
                'x-everee-tenant-id: '.$this->company_id,
            ];
            $response = curlRequest($url, $fields, $headers, $method);
            $resp = json_decode($response, true);
            if (! isset($resp['id'])) {
                $untrackIds['fail'] = [
                    'user_id' => $user->id,
                    'employee_id' => $user->employee_id,
                    'everee_workerId' => $user->everee_workerId,
                    'everee_response' => $response,
                    'status' => false,
                ];
            } else {
                $untrackIds['success'] = [
                    'everee_response' => $response,
                    'status' => true,
                    'externalId' => $resp['id'],
                ];

            }
        }

        return $untrackIds;
    }

    public function payable_request_one_time($user)
    {
        $token = $this->gettoken();
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $untrackIds = [];

        if (! empty($user->everee_workerId)) {
            $fields = json_encode([
                'externalWorkerIds' => [$user->employee_id],

            ]);
            $url = 'https://api-prod.everee.com/api/v2/payables/payment-request';
            $method = 'POST';
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'accept: application/json',
                'content-type: application/json',
                'x-everee-tenant-id: '.$this->company_id,
            ];
            $response = curlRequest($url, $fields, $headers, $method);
            $resp = json_decode($response, true);

            if (! isset($resp['id'])) {
                $untrackIds['fail'] = [
                    'user_id' => $user->id,
                    'employee_id' => $user->employee_id,
                    'everee_workerId' => $user->everee_workerId,
                    'status' => false,
                    'everee_response' => $response,
                ];
            }
            // else
            // {
            //     $untrackIds['success'] = [
            //     'status' => true,
            //     'paymentId' => $resp['id'],
            //     'everee_response' => $response,
            //     ];

            // }

            else {
                // $untrack['paymentId'] = $resp['id'];
                $untrackIds['success'] = [
                    'status' => true,
                    'paymentId' => $resp['id'],
                    'everee_response' => $response,
                ];
                $user_id = $user->id;
                $get_payable_data = $this->get_payable_by_id($user->employee_id, $resp['id']);
                if (isset($get_payable_data['items']) && ! empty($get_payable_data['items'])) {
                    $untrackIds['success']['everee_payment_id'] = $get_payable_data['items'][0]['paymentId'];
                }

                // if(!empty($get_payable_data['items']))
                // {
                //     if(isset($get_payable_data['items'][0]['payablePaymentRequestId']) && $get_payable_data['items'][0]['payablePaymentRequestId'] == $resp['id'] && $get_payable_data['items'][0]['externalWorkerId']==$user->employee_id)
                //     {
                //     $this->update_payment_id_onetime($get_payable_data,$user_id);
                //     }
                // }

            }
        }

        return $untrackIds;
    }

    public function getOnetimePaymentHistory(Request $request, OneTimePayments $OneTimePayments): JsonResponse
    {

        $id = $request->id;
        $paymentHistory = [];
        $search = $request->search;
        // $status =$request->status;
        $filter = $request->filter;
        $type = $request->type; // New parameter: type

        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $result = [];
        $OneTimePayments = $OneTimePayments->newQuery();

        // if($request->has('status'))
        // {
        //     $filterDataStatusWise = $request->input('status');

        //     if($filterDataStatusWise == 'all_status'){
        //       $OneTimePayments;
        //     }else
        //     if($filterDataStatusWise == 'success'){
        //         $OneTimePayments->where('status','success');
        //     }else
        //     if($filterDataStatusWise == 'pending'){
        //         $OneTimePayments->where('status','pending');
        //     }else
        //     if($filterDataStatusWise == 'failed'){
        //         $OneTimePayments->where('status','failed');
        //     }
        // }

        if ($request->has('search')) {
            $OneTimePayments->whereHas(
                'userData', function ($query) use ($request) {
                    $query->where('first_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->search.'%']);
                });
        }

        if (! empty($type)) {
            $OneTimePayments->whereHas('adjustment', function ($query) use ($type) {
                $query->where('name', $type);
            });
        }

        if ($request->has('filter')) {
            $filterDataDateWise = $request->input('filter');
            if ($filterDataDateWise == 'custom') {
                $startDate = date('Y-m-d', strtotime($request->input('start_date')));
                $endDate = date('Y-m-d', strtotime($request->input('end_date').' +1 day'));
                $OneTimePayments->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()->endOfWeek()));
                $OneTimePayments->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
                $OneTimePayments->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_month') {

                $startOfMonth = Carbon::now()->subDays(0)->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));

                $OneTimePayments->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->endOfMonth()));

                $OneTimePayments->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d');

                $OneTimePayments->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                $OneTimePayments->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
                $OneTimePayments->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
                $OneTimePayments->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
                $OneTimePayments->whereBetween('created_at', [$startDate, $endDate]);

            }
        }
        if ($request->has('status')) {
            $filterDataStatusWise = $request->input('status');
            if ($filterDataStatusWise == 'pending') {
                $statusFilter = 0;
                $OneTimePayments->where('everee_payment_status', $statusFilter);
            } elseif ($filterDataStatusWise == 'success') {
                $statusFilter = 1;
                $OneTimePayments->where('everee_payment_status', $statusFilter);
            } elseif ($filterDataStatusWise == 'failed') {
                $statusFilter = 2;
                $OneTimePayments->where('everee_payment_status', $statusFilter);
            } elseif ($filterDataStatusWise == 'all_status') {
                $statusFilter = [0, 1, 2];
                $OneTimePayments->whereIn('everee_payment_status', $statusFilter);
            }
        }

        $paymentHistory = $OneTimePayments->with('userData', 'adjustment')
            ->select('id', 'user_id', 'adjustment_type_id', 'description', 'req_no', 'amount', 'created_at', 'everee_payment_status', 'payment_status', 'everee_external_id', 'everee_paymentId', 'everee_webhook_response')->where('payment_status', 3)->orderBy('id', 'desc')->get();
        if (count($paymentHistory) > 0) {
            foreach ($paymentHistory as $key => $payment) {
                if (isset($payment->userData->image) && $payment->userData->image != null) {
                    // $result['userData']['image_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$payment->userData->image);
                    $res_user = s3_getTempUrl(config('app.domain_name').'/'.$payment->userData->image);
                } else {
                    // $result['userData']['image_s3'] = null;
                    $res_user = null;
                }
                if ($payment->payment_status == 3 && $payment->everee_payment_status == 0) {
                    $status = 'pending';
                } elseif ($payment->payment_status == 3 && $payment->everee_payment_status == 1) {
                    $status = 'success';
                } elseif ($payment->payment_status == 3 && $payment->everee_payment_status == 2) {
                    $status = 'failed';
                } else {
                    $status = 'all';
                }

                if ($paymentHistory[$key]['everee_payment_status'] == 1) {
                    $everee_webhook_message = 'Payment Success From Everee ';
                } elseif ($paymentHistory[$key]['everee_payment_status'] == 2 && $paymentHistory[$key]['everee_webhook_response'] != null && $paymentHistory[$key]['everee_webhook_response'] != '') {
                    $everee_webhook_data = json_decode($paymentHistory[$key]['everee_webhook_response'], true);
                    if (isset($everee_webhook_data['paymentStatus']) && $everee_webhook_data['paymentStatus'] == 'ERROR') {
                        // $everee_webhook_message = $everee_webhook_data['paymentErrorMessage'];
                        $everee_webhook_message = data_get($everee_webhook_data, 'paymentErrorMessage', 'Payment Failed');
                    } else {
                        // $everee_webhook_message = $paymentHistory[$key]['everee_webhook_response'];
                        $everee_webhook_message = data_get($paymentHistory[$key], 'everee_webhook_response', 'Payment Failed');
                    }
                } elseif ($paymentHistory[$key]['everee_payment_status'] == 0) {
                    $everee_webhook_message = 'Waiting for payment status to be updated.';
                }
                $result[] = [
                    'id' => $paymentHistory[$key]['id'],
                    'user_id' => $paymentHistory[$key]['user_id'],
                    'adjustment_id' => $paymentHistory[$key]['adjustment_type_id'],
                    'payment_type' => $paymentHistory[$key]['adjustment']['name'],
                    'position_id' => isset($payment->userData->position_id) ? $payment->userData->position_id : null,
                    'sub_position_id' => isset($payment->userData->sub_position_id) ? $payment->userData->sub_position_id : null,
                    'is_super_admin' => isset($payment->userData->is_super_admin) ? $payment->userData->is_super_admin : null,
                    'is_manager' => isset($payment->userData->is_manager) ? $payment->userData->is_manager : null,
                    'created_at' => $paymentHistory[$key]['created_at'],
                    'amount' => $paymentHistory[$key]['amount'],
                    'type' => 'onetimepayment',
                    'req_no' => $paymentHistory[$key]['req_no'],
                    'everee_payment_status' => $paymentHistory[$key]['everee_payment_status'],
                    'payment_status' => $paymentHistory[$key]['payment_status'],
                    'txn_id' => $paymentHistory[$key]['everee_paymentId'],
                    'userData' => $paymentHistory[$key]['userData'],
                    'user_image' => $res_user,
                    'status' => $status,
                    'description' => $paymentHistory[$key]['description'],
                    'everee_response' => $everee_webhook_message,
                ];
            }

        }
        // $paymentRequest = json_decode($paymentHistory);
        $paymentRequest = $this->paginate($result, $perpage, $request['page']);

        // End Anil kumar varma Code -------------------

        return response()->json([
            'ApiName' => 'get OneTime Payment History',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $paymentRequest,
            'type' => 'onetimepayment',

        ], 200);

    }

    public function paginate($items, $perPage = null, $page = null)
    {
        $total = count($items);
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function one_time_payment_pay_stub_list(Request $request): JsonResponse
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $date = date('Y-m-d');
        $result = [];
        $gross_total_amount = 0;
        $net_total_amount = 0;
        if ($request->has('year') && ! empty($request->year)) {
            $year = $request->year;
            $user_id = $request->user_id;
            if (! empty($user_id) && $user_id != 1) {
                $onetime_data = OneTimePayments::whereYear('created_at', $year)->where('user_id', $user_id);
            } else {
                $onetime_data = OneTimePayments::whereYear('created_at', $year);
            }
            $onetime_data = $onetime_data->where(['payment_status' => 3])->orderBy('id', 'desc')->get();

            if (count($onetime_data) > 0) {
                // $result = [];
                // $payrollHistory->transform(function ($data) {
                foreach ($onetime_data as $data) {
                    $creted_date = isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null;
                    $adjustment_id = isset($data->adjustment_type_id) ? $data->adjustment_type_id : 0;
                    $net_pay = isset($data->amount) ? $data->amount : 0;
                    $gross_total = $net_pay;

                    $gross_total_amount += $gross_total;
                    $net_total_amount += $net_pay;

                    $result[] = [
                        'id' => $data->id,
                        'user_id' => $data->user_id,
                        'adjustment_id' => $adjustment_id,
                        'payroll_date' => $creted_date,
                        'gross_total' => $gross_total,
                        'net_pay' => $net_pay,
                        'miscellaneous' => 0,
                        'type' => 'onetimepayment',
                    ];
                }
            }
        }
        $items = paginate($result, $perpage, $page = null);

        return response()->json([
            'ApiName' => 'one_time_payment_pay_stub_list',
            'status' => true,
            'message' => 'Successfully.',
            'gross_total' => $gross_total_amount,
            'net_pay_total' => $net_total_amount,
            'data' => $items,
        ], 200);
    }

    public function one_time_payment_pay_stub_single_old(Request $request): JsonResponse
    {
        $result = [];
        $userId = $request->user_id;
        if ($request->has('onetime_id') && ! empty($request->onetime_id)) {
            $data1 = OneTimePayments::with('userData', 'adjustment')->where(['payment_status' => 3, 'everee_payment_status' => 1])->where('id', $request->onetime_id)->first();

            if ($data1) {
                $creted_date = isset($data1->created_at) ? date('Y-m-d', strtotime($data1->created_at)) : null;
                $adjustment_id = isset($data1->adjustment_type_id) ? $data1->adjustment_type_id : 0;
                $net_pay = isset($data1->amount) ? $data1->amount : 0;
                $gross_total = $net_pay;

                $paystubQuery = paystubEmployee::where('user_id', $userId)
                    ->where('pay_period_from', '=', $data1->pay_date)
                    ->where('pay_period_to', '=', $data1->pay_date);

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
                    // 'company_business_ein as business_ein',
                    'company_business_ein',
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
                $result['CompanyProfile']->business_ein = $result['CompanyProfile']->company_business_ein ?? null;

                if (isset($result['CompanyProfile']) && $result['CompanyProfile'] != null) {
                    $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
                    $s3_bucket_public_url = $S3_BUCKET_PUBLIC_URL->value;
                    if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                        $image_file_path = $s3_bucket_public_url.config('app.domain_name');
                        $file_link = $image_file_path.'/'.$result['CompanyProfile']->logo;
                        $result['CompanyProfile']['company_logo_s3'] = $file_link;
                    }
                }
                $res = OneTimePayments::with('aAndR')->where(['user_id' => $userId, 'payment_status' => '3', 'adjustment_type_id' => 4])->first();
                // if($res != null)
                // {
                // $result['payroll_id'] = $res->aAndR->payroll_id;
                // }
                // else{
                $result['payroll_id'] = 0;
                // }

                $result['pay_stub']['pay_date'] = date('Y-m-d', strtotime(OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('id', $request->onetime_id)->value('pay_date')));
                $result['pay_stub']['net_pay'] = OneTimePayments::where(['id' => $request->onetime_id, 'user_id' => $userId, 'payment_status' => '3'])->sum('amount');

                $result['pay_stub']['pay_period_from'] = '';
                $result['pay_stub']['pay_period_to'] = '';

                $result['pay_stub']['period_sale_count'] = '';
                $result['pay_stub']['ytd_sale_count'] = '';

                /* user data */
                $user = $paystubQuery->with('positionDetailTeam')
                    ->select(
                        'user_first_name as first_name',
                        'user_middle_name as middle_name',
                        'user_last_name as last_name',
                        'user_employee_id as employee_id',
                        // 'user_social_sequrity_no as social_sequrity_no',
                        'user_name_of_bank as name_of_bank',
                        // 'user_routing_no as routing_no',
                        // 'user_account_no as account_no',
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
                        // 'user_business_ein as business_ein',
                        'user_business_ein',
                    )
                    ->first();
                /* encrypt data modificticate set value */
                $user->account_no = $user->user_account_no;
                $user->routing_no = $user->user_routing_no;
                $user->social_sequrity_no = $user->user_social_sequrity_no;
                $user->business_ein = $user->user_business_ein;

                $result['employee'] = $user;
                // $result['employee'] = !empty($data1->userData) ? $data1->userData  :null;

                $result['earnings']['commission']['period_total'] = '';
                $result['earnings']['commission']['ytd_total'] = '';

                $result['earnings']['overrides']['period_total'] = '';
                $result['earnings']['overrides']['ytd_total'] = '';

                $result['earnings']['reconciliation']['period_total'] = '';
                $result['earnings']['reconciliation']['ytd_total'] = '';

                $result['deduction']['standard_deduction']['period_total'] = '';
                $result['deduction']['standard_deduction']['ytd_total'] = '';

                $result['miscellaneous']['adjustment']['period_total'] = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', '!=', 5)->where('adjustment_type_id', '!=', 2)->where('id', $request->onetime_id)->sum('amount');
                $result['miscellaneous']['adjustment']['ytd_total'] = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', '!=', 5)->where('adjustment_type_id', '!=', 2)->where('id', $request->onetime_id)->whereYear('created_at', date('Y'))->sum('amount');

                $result['miscellaneous']['reimbursement']['period_total'] = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3', 'adjustment_type_id' => 2])->where('id', $request->onetime_id)->sum('amount');
                $result['miscellaneous']['reimbursement']['ytd_total'] = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3', 'adjustment_type_id' => 2])->where('id', $request->onetime_id)->whereYear('created_at', date('Y'))->sum('amount');

                $result['type'] = 'onetimepayment';
            }

        }

        return response()->json([
            'ApiName' => 'one_time_payment_pay_stub_single',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $result,
        ], 200);
    }

    public function one_time_payment_pay_stub_single(Request $request): JsonResponse
    {
        $result = [];
        $userId = $request->user_id;
        if ($request->has('onetime_id') && ! empty($request->onetime_id)) {
            $data1 = OneTimePayments::with('userData', 'adjustment')->where(['payment_status' => 3, 'everee_payment_status' => 1])->where('id', $request->onetime_id)->first();

            if ($data1) {
                $creted_date = isset($data1->created_at) ? date('Y-m-d', strtotime($data1->created_at)) : null;
                $adjustment_id = isset($data1->adjustment_type_id) ? $data1->adjustment_type_id : 0;
                $net_pay = isset($data1->amount) ? $data1->amount : 0;
                $gross_total = $net_pay;

                $payperiodand = $this->getOnetimePaymentPaystubPayperiod($request->onetime_id, $userId);
                $payperiodfrequency = $this->getOnetimePaymentPaystubPayperiodFrequency($request->onetime_id, $userId);
                $payperiodStartDate = $payperiodand ? $payperiodand->pay_period_from : '';
                $payperiodEndDate = $payperiodand ? $payperiodand->pay_period_to : '';

                $paystubQuery = paystubEmployee::where('user_id', $userId);
                if (! empty($payperiodand)) {
                    $paystubQuery->where('pay_period_from', '=', $payperiodand->pay_period_from)
                        ->where('pay_period_to', '=', $payperiodand->pay_period_to);
                }

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
                    // 'company_business_ein as business_ein',
                    'company_business_ein',
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
                if ($result['CompanyProfile']) {
                    $result['CompanyProfile']->business_ein = isset($result['CompanyProfile']->company_business_ein)
                        ? $result['CompanyProfile']->company_business_ein
                        : '';
                }
                if (isset($result['CompanyProfile']) && $result['CompanyProfile'] != null) {
                    $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
                    $s3_bucket_public_url = $S3_BUCKET_PUBLIC_URL->value;
                    if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                        $image_file_path = $s3_bucket_public_url.config('app.domain_name');
                        $file_link = $image_file_path.'/'.$result['CompanyProfile']?->logo;
                        $result['CompanyProfile']['company_logo_s3'] = $file_link;
                    }
                }
                $res = OneTimePayments::with('aAndR')->where(['user_id' => $userId, 'payment_status' => '3', 'adjustment_type_id' => 4])->first();
                // if($res != null)
                // {
                // $result['payroll_id'] = $res->aAndR->payroll_id;
                // }
                // else{
                $result['payroll_id'] = 0;
                // }

                $result['pay_stub']['pay_date'] = date('Y-m-d', strtotime(OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('id', $request->onetime_id)->value('pay_date')));
                $result['pay_stub']['net_pay'] = OneTimePayments::where(['id' => $request->onetime_id, 'user_id' => $userId, 'payment_status' => '3'])->sum('amount');
                $result['pay_stub']['ytd_net_pay'] = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->whereYear('created_at', date('Y'))->sum('amount');

                $payperiodand = $this->getOnetimePaymentPaystubPayperiod($request->onetime_id, $userId);
                $payperiodfrequency = $this->getOnetimePaymentPaystubPayperiodFrequency($request->onetime_id, $userId);
                $payperiodStartDate = $payperiodand ? $payperiodand->pay_period_from : '';
                $payperiodEndDate = $payperiodand ? $payperiodand->pay_period_to : '';
                $result['pay_stub']['pay_frequency'] = $payperiodfrequency ? $payperiodfrequency['name'] : '';
                $result['pay_stub']['pay_period_from'] = $payperiodand ? $payperiodand->pay_period_from : '';
                $result['pay_stub']['pay_period_to'] = $payperiodand ? $payperiodand->pay_period_to : '';
                $result['pay_stub']['period_sale_count'] = OneTimePayments::where(['id' => $request->onetime_id, 'user_id' => $userId, 'payment_status' => '3'])->count();
                $result['pay_stub']['periodeCustomeFieldsSum'] = 0;
                $result['pay_stub']['ytdCustomeFieldsSum'] = 0;
                $result['pay_stub']['ytd_sale_count'] = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->whereYear('created_at', date('Y'))->count();

                /* user data */
                $user = $paystubQuery->with('positionDetailTeam')
                    ->select(
                        'user_first_name as first_name',
                        'user_middle_name as middle_name',
                        'user_last_name as last_name',
                        'user_employee_id as employee_id',
                        // 'user_social_sequrity_no as social_sequrity_no',
                        'user_name_of_bank as name_of_bank',
                        // 'user_routing_no as routing_no',
                        // 'user_account_no as account_no',
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
                        // 'user_business_ein as business_ein',
                        'user_business_ein',
                    )
                    ->first();
                /* encrypt data modificticate set value */
                if ($user) {
                    $user->account_no = isset($user->user_account_no) ? $user->user_account_no : '';
                    $user->routing_no = isset($user->user_routing_no) ? $user->user_routing_no : '';
                    $user->social_sequrity_no = isset($user->user_social_sequrity_no) ? $user->user_social_sequrity_no : '';
                    $user->business_ein = isset($user->user_business_ein) ? $user->user_business_ein : '';
                }

                $result['employee'] = $user;
                // $result['employee'] = !empty($data1->userData) ? $data1->userData  :null;

                $result['earnings']['commission']['period_total'] = $this->otpPaystubSinglePeriodTotal($userId, $request->onetime_id, 'commission', $payperiodStartDate, $payperiodEndDate);
                $result['earnings']['commission']['ytd_total'] = $this->otpPaystubSingleytdtotal($userId, $request->onetime_id, 'commission', $payperiodStartDate, $payperiodEndDate);

                $result['earnings']['overrides']['period_total'] = $this->otpPaystubSinglePeriodTotal($userId, $request->onetime_id, 'overrides', $payperiodStartDate, $payperiodEndDate);
                $result['earnings']['overrides']['ytd_total'] = $this->otpPaystubSingleytdtotal($userId, $request->onetime_id, 'overrides', $payperiodStartDate, $payperiodEndDate);

                $result['earnings']['reconciliation']['period_total'] = $this->otpPaystubSinglePeriodTotal($userId, $request->onetime_id, 'reconciliation', $payperiodStartDate, $payperiodEndDate);
                $result['earnings']['reconciliation']['ytd_total'] = $this->otpPaystubSingleytdtotal($userId, $request->onetime_id, 'reconciliation', $payperiodStartDate, $payperiodEndDate);

                $result['earnings']['additional']['period_total'] = $this->otpPaystubSinglePeriodTotal($userId, $request->onetime_id, 'additional', $payperiodStartDate, $payperiodEndDate);
                $result['earnings']['additional']['ytd_total'] = $this->otpPaystubSingleytdtotal($userId, $request->onetime_id, 'additional', $payperiodStartDate, $payperiodEndDate);

                $result['earnings']['wages']['period_total'] = $this->otpPaystubSinglePeriodTotal($userId, $request->onetime_id, 'wages', $payperiodStartDate, $payperiodEndDate);
                $result['earnings']['wages']['ytd_total'] = $this->otpPaystubSingleytdtotal($userId, $request->onetime_id, 'wages', $payperiodStartDate, $payperiodEndDate);

                $result['deduction']['standard_deduction']['period_total'] = $this->otpPaystubSinglePeriodTotal($userId, $request->onetime_id, 'standard_deduction', $payperiodStartDate, $payperiodEndDate);
                $result['deduction']['standard_deduction']['ytd_total'] = $this->otpPaystubSingleytdtotal($userId, $request->onetime_id, 'standard_deduction', $payperiodStartDate, $payperiodEndDate);

                $result['miscellaneous']['adjustment']['period_total'] = $this->otpPaystubSinglePeriodTotal($userId, $request->onetime_id, 'adjustment', $payperiodStartDate, $payperiodEndDate);
                $result['miscellaneous']['adjustment']['ytd_total'] = $this->otpPaystubSingleytdtotal($userId, $request->onetime_id, 'adjustment', $payperiodStartDate, $payperiodEndDate);

                $result['miscellaneous']['reimbursement']['period_total'] = $this->otpPaystubSinglePeriodTotal($userId, $request->onetime_id, 'reimbursement', $payperiodStartDate, $payperiodEndDate);
                $result['miscellaneous']['reimbursement']['ytd_total'] = $this->otpPaystubSingleytdtotal($userId, $request->onetime_id, 'reimbursement', $payperiodStartDate, $payperiodEndDate);

                $result['type'] = 'onetimepayment';
            }

        }

        return response()->json([
            'ApiName' => 'one_time_payment_pay_stub_single',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $result,
        ], 200);
    }

    public function getOnetimePaymentPaystubPayperiod($onetime_id, $userId)
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

    public function getOnetimePaymentPaystubPayperiodFrequency($onetime_id, $userId)
    {
        if ($userId) {
            $usersubposition = User::FindOrFail($userId);
            if ($usersubposition->sub_position_id) {
                $PositionPayFrequency = PositionPayFrequency::with('frequencyType')->where('position_id', $usersubposition->sub_position_id)->first();
                if ($PositionPayFrequency) {
                    $payfrequency = ['id' => $PositionPayFrequency->frequencyType->id, 'name' => $PositionPayFrequency->frequencyType->name];
                } else {
                    $payfrequency = [];
                }
            } else {
                $payfrequency = [];
            }
        } else {
            $payfrequency = [];
        }

        return $payfrequency;
    }

    public function otpPaystubSinglePeriodTotal($userId, $oneTimePaymentId, $type, $payperiodStartDate, $payperiodEndDate)
    {

        if ($type == 'adjustment') {
            $adjustmentwithoutpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', '!=', 5)->where('adjustment_type_id', '!=', 12)->where('adjustment_type_id', '!=', 2)->where('id', $oneTimePaymentId)->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $adjustmentwithpayroll = PayrollAdjustmentDetail::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->where(['payroll_type' => 'commission'])->sum('amount');
                if (empty($adjustmentwithpayroll)) {
                    $comm_over_dedu_aadjustment = PayrollAdjustmentLock::where(['is_mark_paid' => '0', 'user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePaymentId])->sum(\DB::raw('commission_amount + overrides_amount + deductions_amount'));
                    $reim_claw_recon_aadjustment = PayrollAdjustmentLock::where(['is_mark_paid' => '0', 'user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePaymentId])->sum(\DB::raw('adjustments_amount + reimbursements_amount + clawbacks_amount + reconciliations_amount'));
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

    public function otpPaystubSingleytdtotal($userId, $oneTimePaymentId, $type, $payperiodStartDate, $payperiodEndDate)
    {

        if ($type == 'adjustment') {
            $adjustmentwithoutpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', '!=', 5)->where('adjustment_type_id', '!=', 12)->where('adjustment_type_id', '!=', 2)->whereYear('created_at', date('Y'))->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $adjustmentwithpayroll = PayrollAdjustmentDetailLock::where('user_id', $userId)->where('is_onetime_payment', 1)->where(['payroll_type' => 'commission'])->whereYear('updated_at', date('Y'))->sum('amount');
                if (empty($adjustmentwithpayroll)) {
                    $adjustmentwithpayroll = PayrollHistory::where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 1])->where('pay_period_to', '<=', $payperiodEndDate)->whereYear('pay_period_from', date('Y', strtotime($payperiodStartDate)))->where('payroll_id', '!=', 0)->sum('adjustment');
                }
            } else {
                $adjustmentwithpayroll = $adjustmentwithoutpayroll;
            }

            return $totaladjustment = $adjustmentwithpayroll;
        }

        if ($type == 'overrides') {
            $overrideswithpayroll = 0;
            $overridewithoutpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 11)->where('adjustment_type_id', '!=', 12)->whereYear('created_at', date('Y'))->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = UserOverridesLock::where('user_id', $userId)->where('is_onetime_payment', 1)->whereYear('updated_at', date('Y'))->sum('amount');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'commission') {
            $overrideswithpayroll = 0;
            // $overridewithoutpayroll = OneTimePayments::where(['user_id'=>$userId,'payment_status'=>'3'])->where('adjustment_type_id','!=',12)->sum('amount');
            $overridewithoutpayroll = '';
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = UserCommission::where('user_id', $userId)->where('is_onetime_payment', 1)->whereYear('updated_at', date('Y'))->sum('amount');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'standard_deduction') {
            $overrideswithpayroll = 0;
            // $overridewithoutpayroll = OneTimePayments::where(['user_id'=>$userId,'payment_status'=>'3'])->where('adjustment_type_id',11)->where('adjustment_type_id','!=',12)->sum('amount');
            $overridewithoutpayroll = '';
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = PayrollDeductions::where('user_id', $userId)->where('is_onetime_payment', 1)->whereYear('updated_at', date('Y'))->sum('total');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'reimbursement') {
            $overrideswithpayroll = 0;
            $overridewithoutpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 2)->where('adjustment_type_id', '!=', 12)->whereYear('created_at', date('Y'))->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = ApprovalsAndRequest::where('user_id', $userId)->where('is_onetime_payment', 1)->where('adjustment_type_id', 2)->whereYear('updated_at', date('Y'))->sum('amount');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'reconciliation') {
            $overrideswithpayroll = 0;
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = ReconciliationFinalizeHistory::where('user_id', $userId)->where('is_onetime_payment', 1)->where('status', 3)->whereYear('updated_at', date('Y'))->sum('net_amount');
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'additional') {
            $overrideswithpayroll = 0;
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = CustomFieldHistory::where('user_id', $userId)->where('is_onetime_payment', 1)->whereYear('updated_at', date('Y'))->sum('value');
                if ($overrideswithpayroll == 0) {
                    $overrideswithpayroll = CustomField::with(['getColumn', 'getApprovedBy'])->where('user_id', $userId)->where('is_onetime_payment', 1)->whereYear('updated_at', date('Y'))->sum('value');
                }
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'wages') {
            $overrideswithpayroll = 0;
            // $overridewithoutpayroll = OneTimePayments::where(['user_id'=>$userId,'payment_status'=>'3'])->where('adjustment_type_id',2)->where('adjustment_type_id','!=',12)->whereYear('created_at',date('Y'))->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                if ($payperiodStartDate && $payperiodEndDate) {
                    $hourlySalarySumYtd = PayrollHourlySalaryLock::where('user_id', $userId)->where('is_onetime_payment', 1)->where('pay_period_to', '<=', $payperiodEndDate)->whereYear('pay_period_from', date('Y', strtotime($payperiodStartDate)))->where('payroll_id', '!=', 0)->sum('total');
                    $overtimeSumYtd = PayrollOvertimeLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $payperiodEndDate)->whereYear('pay_period_from', date('Y', strtotime($payperiodStartDate)))->where('payroll_id', '!=', 0)->sum('total');
                } else {
                    $hourlySalarySumYtd = PayrollHourlySalaryLock::where('user_id', $userId)->where('is_onetime_payment', 1)->whereYear('updated_at', date('Y'))->where('payroll_id', '!=', 0)->sum('total');
                    $overtimeSumYtd = PayrollOvertimeLock::where(['user_id' => $userId, 'status' => '3'])->whereYear('updated_at', date('Y'))->where('payroll_id', '!=', 0)->sum('total');
                }
                $overrideswithpayroll = $hourlySalarySumYtd + $overtimeSumYtd;

            } else {
                // $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

    }

    public function one_timepayStubAdjustmentDetails(Request $request): JsonResponse
    {

        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;

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

                // $adjustmentwithpayroll = PayrollAdjustmentDetail::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->where([ 'payroll_type'=> 'commission'])->sum('amount');
                // if(empty($adjustmentwithpayroll)){
                // $comm_over_dedu_aadjustment = PayrollAdjustmentLock::where(['is_mark_paid'=> '0', 'user_id'=>$userId, 'is_onetime_payment'=>1, 'one_time_payment_id' => $oneTimePaymentId])->sum(\DB::raw('commission_amount + overrides_amount + deductions_amount'));
                // $reim_claw_recon_aadjustment = PayrollAdjustmentLock::where(['is_mark_paid'=> '0', 'user_id'=>$userId, 'is_onetime_payment'=>1, 'one_time_payment_id' => $oneTimePaymentId])->sum(\DB::raw('adjustments_amount + reimbursements_amount + clawbacks_amount + reconciliations_amount'));
                // $adjustmentToAdd = ApprovalsAndRequest::where(['user_id'=>$userId, 'is_onetime_payment'=>1, 'one_time_payment_id' => $oneTimePaymentId])->where(['is_mark_paid'=> '0','status'=>'Paid'])->whereIn('adjustment_type_id', [1, 3, 4, 6])->sum('amount');
                // $adjustmentToNigative = ApprovalsAndRequest::where(['user_id'=>$userId, 'is_onetime_payment'=>1, 'one_time_payment_id' => $oneTimePaymentId])->where(['is_mark_paid'=> '0','status'=>'Paid'])->where('adjustment_type_id', 5)->sum('amount');
                // $adjustmentwithpayroll = ($adjustmentToAdd - $adjustmentToNigative) + ($comm_over_dedu_aadjustment + $reim_claw_recon_aadjustment);
                // }

                $adjustment = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->get();
                $adjustmentNegative = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->whereIn('adjustment_type_id', [5])->get();
                // dd($adjustmentNegative);

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
                // dd($PayrollAdjustmentDetail);
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

            return response()->json([
                'ApiName' => 'one_time_adjustment_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => 0,
                'data' => $data,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'one_time_adjustment_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function one_timepayStubReimbursementDetails(Request $request): JsonResponse
    {

        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;

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

            return response()->json([
                'ApiName' => 'one_time_reimbursement_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => 0,
                'data' => $data,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'one_time_reimbursement_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function one_timepayStubUserOverrideDetails(Request $request): JsonResponse
    {

        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;

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

            $sub_total = 0;
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $user_id, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $id)->get();
            if ($onetimepaymentcheckwithpayroll) {

                $userdata = UserOverridesLock::where(['overrides_settlement_type' => 'during_m2'])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->get();

                if (count($userdata) > 0) {

                    foreach ($userdata as $key => $value) {

                        $adjustmentAmount = PayrollAdjustmentDetailLock::where(['pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->first();

                        $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
                        $sale = SalesMaster::where(['pid' => $value->pid])->first();
                        $sub_total = ($sub_total + $value->amount);
                        $data['data'][] = [
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
                    $data['sub_total'] = $sub_total;
                }
            }

            return response()->json([
                'ApiName' => 'one_time_user_override_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => 0,
                'data' => $data,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'one_time_user_override_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function one_timepayStubCommissionDetails(Request $request)
    {

        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;

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
                // return $clawbackSettlement;
                $subtotal = 0;
                if (count($usercommission) > 0) {
                    foreach ($usercommission as $key => $value) {
                        $adjustmentAmount = PayrollAdjustmentDetailLock::where(['pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->amount_type])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->first();

                        if ($value->amount_type == 'm1') {
                            $date = isset($value->saledata->m1_date) ? $value->saledata->m1_date : '';
                        } else {
                            $date = isset($value->saledata->m2_date) ? $value->saledata->m2_date : '';
                        }
                        $data['data'][] = [
                            'id' => $value->id,
                            'pid' => $value->pid,
                            'is_mark_paid' => $value->is_mark_paid,
                            'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
                            'customer_state' => isset($value->saledata->customer_state) ? strtoupper($value->saledata->customer_state) : null,
                            // 'rep_redline' => isset($value->userdata->redline) ? $value->userdata->redline : null,
                            'rep_redline' => $this->formatRedline($value->redline, $value->redline_type),
                            'comp_rate' => number_format($value->comp_rate ?? 0, 4, '.', ''),
                            'kw' => isset($value->saledata->kw) ? $value->saledata->kw : null,
                            'net_epc' => isset($value->saledata->net_epc) ? $value->saledata->net_epc : null,
                            'amount' => isset($value->amount) ? $value->amount : null,
                            // 'date' => isset($value->date) ? $value->date : null,
                            'date' => isset($date) ? $date : null,
                            'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                            'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                            'amount_type' => $this->formatAmountType($value->schema_type, $value->amount_type),
                            'adders' => isset($value->saledata->adders) ? $value->saledata->adders : null,
                            'adjustAmount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                            'product' => isset($value->saledata->product_code) ? $value->saledata->product_code : null,
                            'gross_value' => isset($value->saledata->gross_account_value) ? $value->saledata->gross_account_value : null,
                            'service_schedule' => isset($value->saledata->service_schedule) ? $value->saledata->service_schedule : null,
                            'is_move_to_recon' => $value->is_move_to_recon,
                            'commission_amount' => $value->commission_amount ?? null,
                            'commission_type' => $value->commission_type ?? null,

                        ];
                        $subtotal = ($subtotal + $value->amount);
                    }
                    $data['subtotal'] = $subtotal;
                }

                if (count($clawbackSettlement) > 0) {
                    foreach ($clawbackSettlement as $key1 => $val) {
                        $data['data'][] = [
                            'id' => $val->id,
                            'pid' => $val->pid,
                            'is_mark_paid' => $val->is_mark_paid,
                            'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                            'customer_state' => isset($val->salesDetail->customer_state) ? strtoupper($val->salesDetail->customer_state) : null,
                            'rep_redline' => $this->formatRedline($val->redline, $val->redline_type),
                            'comp_rate' => number_format(0, 4, '.', ''), // Clawbacks typically don't have comp_rate
                            'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                            'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
                            'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
                            'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
                            'pay_period_from' => isset($val->pay_period_from) ? $val->pay_period_from : null,
                            'pay_period_to' => isset($val->pay_period_to) ? $val->pay_period_to : null,
                            'amount_type' => 'clawback',
                            'adders' => isset($val->salesDetail->adders) ? $val->salesDetail->adders : null,
                            'adjustAmount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                            'product' => isset($val->salesDetail->product_code) ? $val->salesDetail->product_code : null,
                            'gross_value' => isset($val->salesDetail->gross_account_value) ? $val->salesDetail->gross_account_value : null,
                            'service_schedule' => isset($val->salesDetail->service_schedule) ? $val->salesDetail->service_schedule : null,
                            'is_move_to_recon' => $val->is_move_to_recon,
                            'commission_amount' => $val->clawback_cal_amount ?? null,
                            'commission_type' => $val->clawback_cal_type ?? null,
                        ];
                        $subtotal = ($subtotal - $val->clawback_amount);
                    }
                    $data['subtotal'] = $subtotal;
                }
            }

            return response()->json([
                'ApiName' => 'one_time_commission_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => 0,
                'data' => $data,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'one_time_commission_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function one_timepayStubPayrollDeductionsDetails(Request $request): JsonResponse
    {

        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;

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
                $paydata = [];
                $Payroll_status = '';
                if (! empty($payroll)) {
                    $Payroll_status = 3;
                    $paydata = PayrollDeductions::with('costcenter')->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->get();
                }

                $response_arr = [];
                $subtotal = 0;
                foreach ($paydata as $d) {
                    $subtotal = $d->subtotal;
                    $response_arr[] = [
                        'Type' => $d->costcenter->name,
                        'Amount' => $d->amount,
                        'Limit' => $d->limit,
                        'Total' => $d->total,
                        'Outstanding' => $d->outstanding,
                        'cost_center_id' => $d->cost_center_id,
                    ];
                }

                $response = ['list' => $response_arr, 'subtotal' => $subtotal];
            }

            return response()->json([
                'ApiName' => 'one_time_payroll_deduction_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => 0,
                'data' => $data,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'one_time_payroll_deduction_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function one_timepayStubReconciliationDetails(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required|exists:users,id',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;

        if (! empty($user_id)) {

            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $user_id, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $id)->get();
            if ($onetimepaymentcheckwithpayroll) {
                $datas = ReconciliationFinalizeHistory::where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->get();
                $myArray = [];
                if (count($datas) > 0) {
                    foreach ($datas as $data) {
                        $commission = $data->commission;
                        $payout = $data->payout;
                        $override = $data->override;
                        // $totalCommission = ($commission*$payout)/100;
                        // $totalOverride = ($override*$payout)/100;
                        $totalCommission = $data->paid_commission;
                        $totalOverride = $data->paid_override;
                        $clawback = $data->clawback;
                        $adjustments = $data->adjustments;
                        $total = ($data->net_amount - $clawback + $adjustments);

                        $myArray[] = [
                            'added_to_payroll_on' => Carbon::parse($data->updated_at)->format('m-d-Y h:s:a'),
                            'startDate_endDate' => Carbon::parse($data->start_date)->format('m/d/Y').' to '.Carbon::parse($data->end_date)->format('m/d/Y'),
                            'commission' => $totalCommission,
                            'override' => $totalOverride,
                            'clawback' => (-1 * $clawback),
                            'adjustment' => $adjustments,
                            'total' => $total,
                            'payout' => $payout,
                            // "finalize_count" => $data->finalize_count,
                            'finalize_count' => $data->finalize_id,
                            'finalize_id' => $data->finalize_id,
                            'start_date' => $data->start_date,
                            'end_date' => $data->end_date,
                        ];
                    }
                }
            }

            return response()->json([
                'ApiName' => 'one_time_reconciliation_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => 0,
                'data' => $myArray,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'one_time_reconciliation_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function one_timepayStubAdditionalDetails(Request $request): JsonResponse
    {

        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;

        if (! empty($user_id)) {
            $AdditionalDetailData = [];
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

                        $AdditionalDetailData[] = [
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

            return response()->json([
                'ApiName' => 'one_time_additional_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => 0,
                'data' => $AdditionalDetailData,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'one_time_additional_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function one_timepayStubWagesDetails(Request $request): JsonResponse
    {

        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;

        if (! empty($user_id)) {
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $user_id, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $id)->get();
            if ($onetimepaymentcheckwithpayroll) {
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
                    ->where('payroll_hourly_salary_lock.user_id', $user_id)->where('payroll_hourly_salary_lock.is_onetime_payment', 1)->where('payroll_hourly_salary_lock.one_time_payment_id', $id)
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

                    return response()->json([
                        'ApiName' => 'one_time_wages_details',
                        'status' => true,
                        'message' => 'Successfully.',
                        'payroll_status' => $Payroll_status,
                        'data' => $response,
                    ], 200);
                }

                return response()->json([
                    'ApiName' => 'one_time_wages_details',
                    'status' => true,
                    'message' => 'No Records.',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'ApiName' => 'one_time_wages_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'one_time_wages_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    /**
     * Format redline value with type (matching regular commission API)
     *
     * @param  mixed  $redline
     * @param  mixed  $redlineType
     * @return mixed
     */
    private function formatRedline($redline, $redlineType)
    {
        if (! $redlineType || ! $redline) {
            return $redline;
        }

        if (in_array($redlineType, config('global_vars.REDLINE_TYPE_ARRAY', []))) {
            return $redline.' Per Watt';
        } else {
            return $redline.' '.ucwords($redlineType);
        }
    }

    /**
     * Format amount type (matching regular commission API)
     *
     * @param  mixed  $schemaType
     * @param  mixed  $amountType
     */
    private function formatAmountType($schemaType, $amountType): string
    {
        if (! $schemaType) {
            return $amountType;
        }

        $type = $schemaType.' Payment';
        if ($amountType == 'm2 update') {
            $type = $schemaType.' Payment Update';
        }

        return $type;
    }
}
