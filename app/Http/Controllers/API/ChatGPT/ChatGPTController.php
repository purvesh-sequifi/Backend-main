<?php

namespace App\Http\Controllers\API\ChatGPT;

use App\Http\Controllers\Controller;
use App\Models\CrmSetting;
use App\Models\SequiaiPlan;
use App\Models\SequiaiRequestHistory;
use App\Models\SubscriptionBillingHistory;
use App\Models\Subscriptions;
use Auth;
use Carbon\Carbon;
use DB;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatGPTController extends Controller
{
    protected $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer '.config('services.chatgpt.token_key'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function askToChatGpt(Request $request): JsonResponse
    {
        $message = $request->type.' '.$request->body;
        // $message = "what is laravel";
        $response = $this->httpClient->post('chat/completions', [
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are'],
                    ['role' => 'user', 'content' => $message],
                ],
            ],
        ]);
        $data = json_decode($response->getBody(), true)['choices'][0]['message']['content'];

        // Save Request to DB for calculate
        $histrory = new SequiaiRequestHistory;
        $histrory->user_id = Auth::id();
        $histrory->user_prompt_type = $request->type;
        $histrory->user_prompt = $request->body;
        // $histrory->response = $data;
        $histrory->save();

        return response()->json([
            'ApiName' => 'Event_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function users_billing_report(Request $request)
    {
        $input = $request->all();
        $Validator = Validator::make(
            $request->all(),
            [
                'page' => 'required',
                'page_size' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $sequAiCrmId = 6;

        if (isset($request->page_size) && $request->page_size != '') {
            $perpage = $request->page_size;
        } else {
            $perpage = 10;
        }

        if (isset($input['column_name']) && ! empty($input['column_name'])) {
            $column = $input['column_name'];
        } else {
            $column = 'id';
        }

        if (isset($input['sort_order']) && ! empty($input['sort_order'])) {
            $sort = $input['sort_order'];
        } else {
            $sort = 'desc';
        }

        $crmSetting = CrmSetting::where(['crm_id' => $sequAiCrmId, 'status' => 1])->first();
        if ($crmSetting) {
            if ($request->start_date != '') {
                $date = Carbon::createFromFormat('Y-m-d', $request->start_date);
                $currentMonth = $date->format('m');
            } else {
                $currentMonth = Carbon::now()->month;
            }

            $crmData = json_decode($crmSetting->value, true);

            $employer_id = @$crmData['employer_id'];
            $currentMonth = Carbon::now()->month;

            $SrDetails = SequiaiRequestHistory::with(['requestedUserDetail', 'requestedPlanDetail'])
                ->whereMonth('billing_date', $currentMonth)
                ->get()->toArray();

            $srList = [];
            $successReportCount = 0;
            $totalPrice = 0;
            $data = [];
            $report = [];
            if (! empty($SrDetails)) {
                // dd($SrDetails);

                foreach ($SrDetails as $list) {
                    $plan_price = 0;
                    $plan_id = 0;
                    $plan_name = '';

                    // $newList['plan'] = $list;
                    $list['price'] = $list['requested_plan_detail']['price'];
                    // dd($list['requested_user_detail']);
                    $report[] = $list;
                    $plan_price = $list['requested_plan_detail']['price'];
                    $totalPrice += $plan_price;
                    $successReportCount++;
                }

                if (! empty($report) && $sort == 'asc') {
                    usort($report, function ($a, $b) {
                        return $a['price'] - $b['price'];
                    });
                } else {
                    usort($report, function ($a, $b) {
                        return $b['price'] - $a['price'];
                    });
                }

                $data = paginate($report, $perpage);

                // dd($data, $successReportCount, $totalPrice);
                return response()->json([
                    'ApiName' => 'user billing report',
                    'status' => true,
                    'message' => 'SequifiAI Request List',
                    'data' => $data,
                    'report_count' => $successReportCount,
                    'total_price' => $totalPrice,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'user billing report',
                    'status' => false,
                    'message' => 'SequifiAI Requests Not Found',
                    'apiResponse' => '',
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'user billing report',
                'status' => false,
                'message' => 'Please Activate SequifiAI First',
            ], 400);
        }
    }

    public function get_plans(Request $request): JsonResponse
    {
        $plans = SequiaiPlan::where('status', 1)->get();

        return response()->json([
            'ApiName' => 'SequifiAI get_plans',
            'status' => true,
            'message' => 'SequifiAI Plans List',
            'data' => $plans,
        ], 200);
    }

    // Same Calculation in GenrateBillingHistory CMD
    public function generateSquiAiBilling($subscription_last, $invoice_no)
    {
        DB::beginTransaction();
        $unique_pid_discount_price = 0;
        $m2_discount_price = 0;
        $balance_credit = 0;
        $permars_array = [];
        $subscription_end_date = $subscription_last->end_date;
        $subscription_start_date = $subscription_last->start_date;
        // Get data for invoice
        $permars_array['subscription_end_date'] = $subscription_end_date;
        $permars_array['subscription_start_date'] = $subscription_start_date;

        // $everee_billing = SalesInvoiceDetail::everee_billing($permars_array);
        $payroll_histry_id_data = $everee_billing['payroll_histry_id_data'] ?? [];
        $one_time_payment_date_data = $everee_billing['one_time_payment_date_data'] ?? [];

        $last_month_payroll_histry_count = count($payroll_histry_id_data);
        $last_payroll_histry_net_pay_sum = array_sum(array_column($payroll_histry_id_data, 'net_pay'));

        $last_month_one_time_payment_count = count($one_time_payment_date_data);
        $last_one_time_payment_net_pay_sum = array_sum(array_column($one_time_payment_date_data, 'net_pay'));

        $total_payment_data_for_invoice = $last_month_payroll_histry_count + $last_month_one_time_payment_count;

        // // Pid calculation
        $last_month_payroll_total_amount = $last_month_payroll_histry_count * $unique_pid_discount_price;

        // m2 date calculation
        $last_month_otp_total_amount = $last_month_one_time_payment_count * $m2_discount_price;

        // sales tax per
        $sales_tax_per = isset($subscription_last->sales_tax_per) && $subscription_last->sales_tax_per > 0 ? $subscription_last->sales_tax_per : 7.25;

        $user_request_ids = [];
        $sequiai_plan_id = 0;
        try {
            // Get SequiAI Plan
            $sequiAiPlanCrmSetting = CrmSetting::where('crm_id', 6)->first();
            $last_month_total = 0;
            if ($sequiAiPlanCrmSetting != null) {
                $sequiAiPlanValue = isset($sequiAiPlanCrmSetting->value) ? json_decode($sequiAiPlanCrmSetting->value, true) : [];
                $sequiai_plan_id = isset($sequiAiPlanValue['sequiai_plan_id']) ? $sequiAiPlanValue['sequiai_plan_id'] : 0;

                $sequiaiPlan = SequiaiPlan::find($sequiai_plan_id);
                if ($sequiaiPlan != null) {
                    $requestData = SequiaiRequestHistory::userRequestBillingData($sequiai_plan_id);
                    $last_month_total = $requestData['bill_amount'] ?? 0;
                    $user_request_ids = $requestData['user_request_ids'] ?? [];
                }
            }
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            DB::rollBack();
        }

        $sales_tax_amount = (($last_month_total * $sales_tax_per) / 100);
        $grand_last_month_total = ($last_month_total + $sales_tax_amount);

        // genrate invoice Number
        // $invoice_no = SubscriptionBillingHistory::genrate_invoice();
        $billing_date = $subscription_last->end_date;
        $plan_id = isset($subscription_last->plans) ? $subscription_last->plans->id : null;
        $plan_name = isset($subscription_last->plans) ? $subscription_last->plans->name : null;
        $product_name = isset($subscription_last->plans) ? $subscription_last->plans->product_name : null;

        $checkSubscriptionBillingHistory = SubscriptionBillingHistory::where('subscription_id', $subscription_last->id)->first();
        if ($checkSubscriptionBillingHistory == null || (isset($checkSubscriptionBillingHistory->amount) && $checkSubscriptionBillingHistory->amount != round($grand_last_month_total, 2))) {
            $datah = [
                'subscription_id' => $subscription_last->id,
                'invoice_no' => $invoice_no,
                'amount' => round($grand_last_month_total, 2),
                'plan_id' => $plan_id,
                'plan_name' => $plan_name,
                'unique_pid_rack_price' => 0,
                'unique_pid_discount_price' => 0,
                'm2_rack_price' => 0,
                'm2_discount_price' => 0,
                'billing_date' => $subscription_last->end_date,
            ];

            $evereeSubscriptionBillingHistory = SubscriptionBillingHistory::Create($datah);
            $billing_history_id = $evereeSubscriptionBillingHistory->id;
        } else {
            $billing_history_id = $checkSubscriptionBillingHistory->id;
        }

        if ($billing_history_id) {
            // Creating New subscription for next month

            $update_subscription = Subscriptions::where('id', '=', $subscription_last->id)->first();

            $endDate = Carbon::parse($update_subscription->end_date);
            $newstartDate = $endDate->copy()->addDay()->startOfMonth();
            $newEndDate = date('Y-m-t', strtotime($newstartDate));

            // Creating New subscription for next month Data
            $create_subscription = [
                'plan_id' => $update_subscription->plan_id,
                'plan_type_id' => $update_subscription->plan_type_id,
                'start_date' => $newstartDate,
                'end_date' => $newEndDate,
                'status' => 1,
            ];

            $update_subscription->total_pid = $last_month_payroll_histry_count;
            $update_subscription->total_m2 = $last_month_one_time_payment_count;
            $update_subscription->sales_tax_per = $sales_tax_per;

            // Creating Billing History
            $subscription_last_month = Carbon::parse($subscription_last->created_at)->format('m');
            $currentMonth = Carbon::now()->month;

            // $update_subscription->status = 0;

            $update_subscription->sales_tax_amount = round($sales_tax_amount, 2);
            $update_subscription->amount = $last_month_total;
            $update_subscription->credit_amount = 0; // $credit_amount;
            $update_subscription->used_credit = 0; // $used_credit;
            $update_subscription->balance_credit = 0; // $balance_credit;
            $update_subscription->taxable_amount = $last_month_total;
            $update_subscription->grand_total = round($grand_last_month_total, 2);
            $update_status = $update_subscription->update();

            if ($update_status) {

                $new_subscription_count = Subscriptions::where('plan_id', $plan_id)->whereDate('start_date', '=', $newstartDate)->whereDate('end_date', '=', $newEndDate)->orderby('subscriptions.id', 'desc')->count();

                if ($new_subscription_count == 0) {

                    // Creating Billing History
                    $subscription_last_month = Carbon::parse($subscription_last->created_at)->format('m');
                    $currentMonth = Carbon::now()->month;

                    // Creating New subscription for next month
                    // $Subscriptions = Subscriptions::Create($create_subscription);

                    // Updated records
                    SequiaiRequestHistory::whereIn('id', $user_request_ids)->update([
                        'status' => 1,
                        'billing_date' => Carbon::now()->format('Y-m-d'),
                        'sequiai_plan_id' => $sequiai_plan_id,
                        'subscription_billing_history_id' => $billing_history_id,
                    ]);
                    // dd("hi");
                    $create_update_credit = [
                        'old_balance_credit' => $balance_credit,
                        'month' => $newEndDate,
                    ];

                    if ($balance_credit > 0) {
                        Credit::updateOrCreate(
                            ['month' => $create_update_credit['month']],
                            $create_update_credit
                        );
                    }

                    DB::commit();
                } else {
                    DB::rollback();
                }
            }
        }
    }
}
