<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AddOnPlans;
use App\Models\BillingType;
use App\Models\Buckets;
use App\Models\BusinessAddress;
use App\Models\CompanyBillingAddress;
use App\Models\CompanyProfile;
use App\Models\Credit;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\OneTimePayments;
use App\Models\PayrollHistory;
use App\Models\Plans;
use App\Models\PlanWithAddOnPlan;
use App\Models\SalesInvoiceDetail;
use App\Models\SalesMaster;
use App\Models\SClearancePlan;
use App\Models\SClearanceTurnScreeningRequestList;
use App\Models\SequiaiPlan;
use App\Models\SequiaiRequestHistory;
use App\Models\StateMVRCost;
use App\Models\StripeResponseLog;
use App\Models\SubscriptionBillingHistory;
use App\Models\Subscriptions;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StripeBillingController extends Controller
{
    use EmailNotificationTrait;
    /**
     * Display a listing of the resource.
     */
    // public function index(Request $request)
    // {
    //     if(!empty($request->perpage)){
    //             $perpage = $request->perpage;
    //         }else{
    //             $perpage = 10;
    //         }
    //     $gtotal = 0;
    //     $history = SubscriptionBillingHistory::orderby('billing_date', 'DESC')->get();
    //     if(count($history) > 0)
    //     {
    //         $history->transform(function ($val) {
    //             $subscription = Subscriptions::with('plans','billingType')->where('id',$val->subscription_id)->first();
    //             $pid_count = $m2_pid_count = $pid_total_amount = $m2_total_amount = null;

    //             $plan_type = isset($subscription->billingType)?$subscription->billingType->name:null;

    //             $plan_id = isset($val->plan_id) ? $val->plan_id : null;
    //             $plan = isset($val->plan_name) ? $val->plan_name : null;
    //             $unique_pid_rack_price = isset($val->unique_pid_rack_price) ? $val->unique_pid_rack_price : null;
    //             $unique_pid_discount_price = isset($val->unique_pid_discount_price) ? $val->unique_pid_discount_price : null;
    //             $m2_rack_price = isset($val->m2_rack_price) ? $val->m2_rack_price : null;
    //             $m2_discount_price = isset($val->m2_discount_price) ? $val->m2_discount_price : null;

    //             $billing_history_id = $val->id;
    //             // unique_pid_data
    //             $unique_pid_data = SalesInvoiceDetail::where('billing_history_id', $billing_history_id)->where('invoice_for' , '=' , 'unique_pid')->get();
    //             $unique_pid_sum = $unique_pid_data->sum('kw');
    //             $pid_total_amount = (double)($unique_pid_sum*1000*$unique_pid_discount_price) ;

    //             // Sales Invoice Detail_m2_date_data
    //             $SalesInvoiceDetail_m2_date_data = SalesInvoiceDetail::where('billing_history_id', $billing_history_id)->where('invoice_for' , '=' , 'm2_date')->get();
    //             $m2_kw_sum = $SalesInvoiceDetail_m2_date_data->sum('kw');
    //             $m2_total_amount = ($m2_kw_sum*1000*$m2_discount_price);

    //             $credit_amount = isset($subscription->credit_amount)?$subscription->credit_amount:0;
    //             $used_credit = isset($subscription->used_credit)?$subscription->used_credit:0;
    //             $balance_credit = isset($subscription->balance_credit)?$subscription->balance_credit:0;
    //             $taxable_amount = isset($subscription->taxable_amount) && $subscription->taxable_amount > 0 ?$subscription->taxable_amount: $subscription->amount;

    //             return [
    //                 'id'=>$val->id,
    //                 'subscription_id' => isset($val->subscription_id)?$val->subscription_id	: null,
    //                 'unique_pid_discount_price' => $unique_pid_discount_price,
    //                 'm2_discount_price' => $m2_discount_price,
    //                 'unique_pid_rack_price' => $unique_pid_rack_price,
    //                 'm2_rack_price' => $m2_rack_price,
    //                 'pid_count' => isset($subscription->total_pid)?$subscription->total_pid : $pid_count,
    //                 'unique_pid_sum'=>round($unique_pid_sum , 2),
    //                 'm2_pid_count' => isset($subscription->total_m2)?$subscription->total_m2 : $m2_pid_count,
    //                 'm2_kw_sum'=>round($m2_kw_sum , 2),
    //                 'pid_total_amount'=>round($pid_total_amount , 2),
    //                 'm2_total_amount'=>round($m2_total_amount , 2),
    //                 'credit_amount'=>$credit_amount,
    //                 'used_credit'=>$used_credit,
    //                 'balance_credit'=>$balance_credit,
    //                 'taxable_amount'=>$taxable_amount,
    //                 'sales_tax_amount' => isset($subscription->sales_tax_amount)?$subscription->sales_tax_amount : null,
    //                 'total' => isset($subscription->amount)?$subscription->amount:null,
    //                 'amount' => isset($subscription->grand_total)?$subscription->grand_total:null,
    //                 'grand_total' => isset($subscription->grand_total)?$subscription->grand_total : null,
    //                 'amount_without_tax' => isset($subscription->amount)?$subscription->amount:null,
    //                 'amount_with_tax' => isset($subscription->grand_total)?$subscription->grand_total:null,
    //                 'plan' => $plan,
    //                 'plan_type' => $plan_type,
    //                 'paid_status' => isset($val->paid_status)?$val->paid_status:null,
    //                 'invoice_no' => isset($val->invoice_no)?$val->invoice_no:null,
    //                 'billing_date' => isset($val->billing_date)?$val->billing_date:null,
    //             ];
    //         });
    //     }
    //     $data = json_decode($history);
    //    $history = paginate($data, $perpage);
    //     return response()->json([
    //         'ApiName' => 'get_billing',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'data' => $history,
    //     ], 200);
    // }

    // added by deepak
    public function index(Request $request): JsonResponse
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $gtotal = 0;
        $details = [];
        $invoice_no = SubscriptionBillingHistory::groupBy('invoice_no')->orderBy('invoice_no', 'DESC')->pluck('invoice_no');
        foreach ($invoice_no as $value) {
            $history = SubscriptionBillingHistory::with('subscription')->where('invoice_no', $value)->get();
            $product_count = 0;
            $grand_total = 0;

            $product_count = $history->count();
            $grand_total = $history->sum(fn ($item) => $item->subscription->grand_total ?? 0);

            if ($grand_total < 1 && isset($history[0]['paid_status']) && $history[0]['paid_status'] == 0) {
                continue;
            }
            $details[] = [
                'product_count' => $product_count,
                'start_date' => isset($history[0]['subscription']['start_date']) ? $history[0]['subscription']['start_date'] : null,
                'end_date' => isset($history[0]['subscription']['end_date']) ? $history[0]['subscription']['end_date'] : null,
                'grand_total' => round($grand_total, 2),
                'paid_status' => isset($history[0]['paid_status']) ? $history[0]['paid_status'] : null,
                'last_payment_message' => isset($history[0]['last_payment_message']) ? $history[0]['last_payment_message'] : null,
                'invoice_no' => isset($history[0]['invoice_no']) ? $history[0]['invoice_no'] : null,
                'payment_url' => isset($history[0]['payment_url']) ? $history[0]['payment_url'] : null,
                'billing_date' => isset($history[0]['billing_date']) ? $history[0]['billing_date'] : null,
                'invoice_id' => (isset($history[0]['client_secret']) && ! empty(($history[0]['client_secret']))) ? openssl_encrypt($history[0]['client_secret'], config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv')) : null,
            ];
        }

        $data = paginate($details, $perpage);

        return response()->json([
            'ApiName' => 'get_billing',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    // get invoice data
    public function get_invoice_data($bill_id): JsonResponse
    {
        $history = SubscriptionBillingHistory::with('subscription.plans', 'subscription.billingType')->where('invoice_no', $bill_id)->get();
        $data = [];
        $status = false;
        $message = 'Invoice not found!';
        $status_code = 400;
        $invoice_data = [];
        $invoice_calculation = [];
        $grand_total = 0;
        $sales_tax_amount = 0;
        $exe_tax_amount = 0;
        $sales_tax_per = 0;
        $product_count = 0;
        $discount_credit = 0;
        $total_credit = 0;
        $texable_amounnt = 0;
        $invoice_no = '';
        $billing_date = '';

        if (! empty($history)) {
            foreach ($history as $key => $historyData) {
                // $subscription = Subscriptions::with('plans','billingType')->where('id',$history->subscription_id)->first();

                $credit_amount = isset($historyData->subscription->credit_amount) ? $historyData->subscription->credit_amount : 0;
                $used_credit = isset($historyData->subscription->used_credit) ? $historyData->subscription->used_credit : 0;
                $balance_credit = isset($historyData->subscription->balance_credit) ? $historyData->subscription->balance_credit : 0;
                $taxable_amount = isset($historyData->subscription->taxable_amount) && $historyData->subscription->taxable_amount > 0 ? $historyData->subscription->taxable_amount : $historyData->subscription->amount;
                $product = isset($historyData->subscription->plans->product_name) ? $historyData->subscription->plans->product_name : $historyData->subscription->plans->product_name;
                $plan_id = isset($historyData->subscription->plans->id) ? $historyData->subscription->plans->id : $historyData->subscription->plans->id;

                // invoice data
                $invoice_no = $historyData->invoice_no;
                $billing_date = $historyData->billing_date;
                $sales_tax_amount += $historyData->subscription->sales_tax_amount;
                // $sales_tax_amount += ($historyData->subscription->grand_total);
                $grand_total += $historyData->subscription->grand_total;
                $exe_tax_amount += $historyData->subscription->amount;
                $texable_amounnt += $taxable_amount;
                $sales_tax_per += $historyData->subscription->sales_tax_per;
                $discount_credit += $historyData->subscription->used_credit;
                $total_credit += $historyData->subscription->credit_amount;
                $product_count++;

                $extra_info = [];
                if ($historyData->plan_id == 4) {
                    $sequiAiPlanCrmSetting = CrmSetting::where('crm_id', 6)->first();

                    $sequiai_plan_id = 0;
                    if ($sequiAiPlanCrmSetting != null) {
                        $sequiAiPlanValue = isset($sequiAiPlanCrmSetting->value) ? json_decode($sequiAiPlanCrmSetting->value, true) : [];
                        $sequiai_plan_id = isset($sequiAiPlanValue['sequiai_plan_id']) ?? 0;
                    }

                    $sequiaiPlan = SequiaiPlan::find($sequiai_plan_id);
                    if ($sequiaiPlan != null) {
                        $subscription_id = $historyData->subscription->id ?? 0;
                        $subscriptionBillingHistory = SubscriptionBillingHistory::where('subscription_id', $subscription_id)->first();

                        $subscription_billing_history_id = $subscriptionBillingHistory->id;

                        $historyIds = SequiaiRequestHistory::where('subscription_billing_history_id', $subscription_billing_history_id)->pluck('id')->toArray();

                        $min_request = (int) $sequiaiPlan->min_request;
                        $min_request_price = (float) $sequiaiPlan->price;

                        $records = count($historyIds) / $min_request;
                        $records_roundup = (int) $records;
                        if (is_float($records)) {
                            $records_roundup++;
                        }
                        if ($records_roundup != 0) {
                            $total = $records_roundup * $min_request_price;
                        } else {
                            $total = $min_request_price;
                        }

                        // $getRrecords = SequiaiRequestHistory::where(['status'=> 0])->pluck('id')->toArray();

                        $totalData['price'] = $min_request_price;
                        $totalData['report_count'] = $records_roundup;
                        $totalData['total'] = $total;

                        if (is_array($totalData)) {
                            $total = $totalData['total'] ?? 0;
                            $price = $totalData['price'] ?? 0;
                            $report_count = $totalData['report_count'] ?? 0;

                            $additioonal_credits = 0;
                            if ($price) {

                            }

                            $extra_info = [
                                'first_credits' => $price,
                                'additioonal_credits' => $total - $price,
                                // 'price_estimates' => $total,
                                'first_credit_discount' => 0,
                                'additioonal_credit_discount' => 0,
                                'grand_total' => $historyData->subscription->grand_total,
                                'taxable_amount' => $taxable_amount,
                                'amount' => $historyData->subscription->amount,
                                'taxable_amount' => $taxable_amount,
                                'grand_total' => $historyData->subscription->grand_total,
                                'sales_tax_per' => $historyData->subscription->sales_tax_per,
                                'exe_tax_amount' => round($historyData->subscription->grand_total - $taxable_amount, 2),
                                'sales_tax_amount' => $historyData->subscription->sales_tax_amount,
                            ];
                        }
                    }
                }

                $invoice_data[] = [
                    // "paid_status" => $historyData->paid_status,
                    // "invoice_no" => $historyData->invoice_no,
                    // "billing_date" => $historyData->billing_date,
                    // "total_pid" => $historyData->subscription->total_pid,
                    // "total_m2" => $historyData->subscription->total_m2,
                    // 'credit_amount'=>$credit_amount,
                    // 'used_credit'=>$used_credit,
                    // 'balance_credit'=>$balance_credit,
                    // "rack_price" => isset($historyData->subscription->plans) ? $historyData->subscription->plans->rack_price : null,

                    'product' => $product,
                    'plan_id' => $plan_id,
                    'amount' => $historyData->subscription->amount,
                    'taxable_amount' => $taxable_amount,
                    'grand_total' => $historyData->subscription->grand_total,
                    'sales_tax_per' => $historyData->subscription->sales_tax_per,
                    'exe_tax_amount' => round($historyData->subscription->grand_total - $taxable_amount, 2),
                    'sales_tax_amount' => $historyData->subscription->sales_tax_amount,
                    // "discount_price" => isset($historyData->subscription->plans) ? $historyData->subscription->used_credit : null,
                    'credit_amount' => isset($historyData->subscription) ? $historyData->subscription->credit_amount : null,
                    'used_credit' => isset($historyData->subscription) ? $historyData->subscription->used_credit : null,
                    'unique_pid_rack_price' => $historyData->unique_pid_rack_price,
                    'unique_pid_discount_price' => $historyData->unique_pid_discount_price,
                    'm2_rack_price' => $historyData->m2_rack_price,
                    'm2_discount_price' => $historyData->m2_discount_price,
                    'extra_info' => $extra_info,
                    'start_date' => isset($historyData->subscription) ? $historyData->subscription->start_date : null,
                    'end_date' => isset($historyData->subscription) ? $historyData->subscription->end_date : null,
                    'invoice_id' => (isset($historyData->client_secret) && ! empty(($historyData->client_secret))) ? openssl_encrypt($historyData->client_secret, config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv')) : null,
                    'paid_status' => $historyData->paid_status,
                ];
            }
            $data['invoice_data'] = $invoice_data;
            $data['invoice_calculation'] = [
                'grand_total' => round($grand_total, 2),
                'sales_tax_amount' => round($sales_tax_amount, 2),
                'exe_tax_amount' => $exe_tax_amount,
                'invoice_no' => $invoice_no,
                'billing_date' => $billing_date,
                'due_date' => (! empty($billing_date)) ? date('m-d-Y', strtotime($billing_date.' +5 days')) : '',
                'texable_amounnt' => $texable_amounnt,
                'sales_tax_per' => ($sales_tax_per / $product_count),
                'discount_credit' => $discount_credit,
                'total_credit' => $total_credit,
                'start_date' => isset($historyData->subscription) ? $historyData->subscription->start_date : null,
                'end_date' => isset($historyData->subscription) ? $historyData->subscription->end_date : null,
                'invoice_id' => (isset($historyData->client_secret) && ! empty(($historyData->client_secret))) ? openssl_encrypt($historyData->client_secret, config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv')) : null,
                'paid_status' => $historyData->paid_status,
            ];
        }
        // bill from data
        $bill_from = [
            'company_name' => 'SEQUIFI INC', 'address' => 'MARTIN NELOSON 2901 W BLUE BLVD STE 200 LEHI, UT 84043', 'email' => 'billing@sequfi.com', 'phone_number' => '1-800-8294-933', 'tax_id' => '92-1108162',
        ];
        $data['bill_from'] = $bill_from;

        // bill to data
        $BusinessAddress = BusinessAddress::first();
        $CompanyBillingAddress = CompanyBillingAddress::first();
        // if Billing Address not created then company profile data will send
        if ($CompanyBillingAddress == null || $CompanyBillingAddress == '') {
            $CompanyBillingAddress = CompanyBillingAddress::create_Company_Billing_Address();
        }
        if ($BusinessAddress == null || $BusinessAddress == '') {
            $BusinessAddress = BusinessAddress::create_Company_Billing_Address();
        }

        $data['bill_to'] = $BusinessAddress;
        $status = true;
        $status_code = 200;
        $message = 'Invoice data get Successfully';

        return response()->json([
            'ApiName' => 'get_invoice_data',
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status_code);
    }

    // not in use
    public function addSubscription(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'plan_type_id' => 'required',
                'plan_id' => 'required',
                'start_date' => 'required',
                'end_date' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $addontotalrackprice = [];
        $addontotaldiscountprice = [];
        $totalplanprice = 0;
        $totaladdonprice = 0;
        $total = 0;
        if (! empty($request->plan_type_id) && ! empty($request->plan_id)) {
            // calculate plan price
            $plan = Plans::where('id', $request->plan_id)->first();
            $plantype = BillingType::where('id', $request->plan_type_id)->first();
            if (! empty($plan->id) && ($plan->id != 0) && (! empty($plantype->id)) && ($plantype->id != 0)) {
                $planrackprice = $plan->rack_price;
                $plandiscountprice = $plan->discount_price;
                $totalplanprice = (float) $planrackprice - (float) $plandiscountprice;

                // calculate add on plan price
                $addonplans = PlanWithAddOnPlan::where('plan_id', $request->plan_id)->get();
                if (count($addonplans) > 0) {
                    foreach ($addonplans as $addon) {
                        $addondata = AddOnPlans::where('id', $addon->id)->first();
                        if (! empty($addondata)) {
                            $addonrack_price = $addondata->rack_price;
                            array_push($addontotalrackprice, $addonrack_price);
                            $addondiscount_price = $addondata->discount_price;
                            array_push($addontotaldiscountprice, $addondiscount_price);
                        }
                    }
                    $totaladdonrackprice = array_sum($addontotalrackprice);
                    $totaladdondiscountprice = array_sum($addontotaldiscountprice);
                    $totaladdonprice = (float) $totaladdonrackprice - (float) $totaladdondiscountprice;
                }
                // total plan price
                $totalcalculatedprice = (float) $totalplanprice + (float) $totaladdonprice;

                $m2date = SalesMaster::whereMonth('customer_signoff', Carbon::now()->month)->where('m2_date', '!=', null)->count('m2_date');
                $pidcount = SalesMaster::whereMonth('customer_signoff', Carbon::now()->month)->count('pid');

                // total final price
                $total = (float) ($m2date * $totalcalculatedprice) + (float) ($pidcount * $totalcalculatedprice);
                $data = [
                    'plan_type_id' => $request->plan_type_id,
                    'plan_id' => $request->plan_id,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'total_pid' => $pidcount,
                    'total_m2' => $m2date,
                    'amount' => $total,

                ];
                $Subscription = Subscriptions::Create($data);
                if ($Subscription) {
                    return response()->json([
                        'ApiName' => 'add_subscription',
                        'status' => true,
                        'message' => 'Successfully.',
                        'data' => $data,
                    ], 200);
                }
            } else {
                return response()->json([
                    'ApiName' => 'add_subscription',
                    'status' => false,
                    'message' => 'Failed.',
                ], 422);
            }
        } else {
            return response()->json([
                'ApiName' => 'add_subscription',
                'status' => false,
                'message' => 'Failed.',
            ], 422);
        }
    }

    // not in use
    public function getSubscriptions(Request $request): JsonResponse
    {
        $subscription = Subscriptions::with('plans', 'billingType')->whereMonth('start_date', Carbon::now()->month)->where('status', 1)->first();
        $data = [];
        if (! empty($subscription)) {
            $planrackprice = isset($subscription->plans) ? $subscription->plans->discount_price : null;
            $frequency = isset($subscription->billingType) ? $subscription->billingType->frequency : null;
            $plan = isset($subscription->plans) ? $subscription->plans->name : null;
            $plan_type = isset($subscription->billingType) ? $subscription->billingType->name : null;
            $totalplanprice = (float) $planrackprice;
            $planid = isset($subscription->plans) ? $subscription->plans->id : null;

            // calculate add on plan price
            // $addonplans =  PlanWithAddOnPlan::where('plan_id',$data->plans->id)->get();
            // if(count($addonplans) > 0)
            // {
            //     foreach($addonplans as $addon)
            //     {
            //         $addondata = AddOnPlans::where('id',$addon->id)->first();
            //         if(!empty($addondata))
            //         {
            //         $addonrack_price = $addondata->rack_price;
            //         array_push($addontotalrackprice,$addonrack_price);
            //         $addondiscount_price = $addondata->discount_price;
            //         array_push($addontotaldiscountprice,$addondiscount_price);
            //         }
            //     }
            // $totaladdonrackprice = array_sum($addontotalrackprice);
            // $totaladdondiscountprice = array_sum($addontotaldiscountprice);
            // $totaladdonprice = (double)$totaladdonrackprice -(double)$totaladdondiscountprice ;
            // }
            $m2kwsum = SalesMaster::whereMonth('customer_signoff', Carbon::now()->month)->where('m2_date', '!=', null)->sum('kw');
            $pidkwsum = SalesMaster::whereMonth('customer_signoff', Carbon::now()->month)->sum('kw');

            // total final price
            $total = (float) ($m2kwsum * 1000 * $totalplanprice) + (float) ($pidkwsum * 1000 * $totalplanprice);
            $data = [
                'id' => $subscription->id,
                'product' => 'Sequifi Payroll',
                'plan' => $plan,
                'plan_type' => $plan_type,
                'frequency' => $frequency,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'amount' => round($total, 2),
                'created_at' => $subscription->created_at,
                'updated_at' => $subscription->updated_at,
            ];

            // add last month data into history
            $subscriptionlast = Subscriptions::with('plans', 'billingType')->whereMonth('start_date', Carbon::now()->subMonth()->month)->first();
            $m2kwsumlastmonth = SalesMaster::whereMonth('customer_signoff', Carbon::now()->subMonth()->month)->where('m2_date', '!=', null)->sum('kw');
            $pidkwsumlastmonth = SalesMaster::whereMonth('customer_signoff', Carbon::now()->subMonth()->month)->sum('kw');
            $planrackpricelast = isset($subscriptionlast->plans) ? $subscriptionlast->plans->discount_price : null;
            $totallastmonth = (float) ($m2kwsumlastmonth * 1000 * $planrackpricelast) + (float) ($pidkwsumlastmonth * 1000 * $planrackpricelast);
            $lastDayofMonth = Carbon::now()->endOfMonth()->toDateString();
            if (date('Y-m-d') == $lastDayofMonth) {
                $datah = [
                    'subscription_id' => $subscriptionlast->id,
                    'amount' => $totallastmonth,
                    'billing_date' => $subscriptionlast->end_date,
                ];
                SubscriptionBillingHistory::Create($datah);
            }

        }

        return response()->json([
            'ApiName' => 'get_subscriptions',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    // create_billing_history
    public function create_billing_history() {}

    // get month wise getSubscriptions
    public function get_monthly_subscriptions(Request $request)
    {
        if ($request->invoice_no != '') {
            $data = SubscriptionBillingHistory::whereHas('plans')->with(['plans' => function ($query) {
                $query->select('id', 'product_name', 'name');
            }, 'subscription' => function ($subscription_qry) {
                $subscription_qry->select('id', 'start_date', 'end_date', 'total_pid as total_payroll', 'total_m2 as total_onetime_payment', 'plan_type_id');
            }, 'subscription.billingType'])->where('invoice_no', $request->invoice_no)->get();
            $data->transform(function ($item) {
                $item = [
                    'id' => $item->id,
                    'subscription_id' => $item->subscription_id,
                    'plan' => $item->plans->name,
                    'product' => $item->plans->product_name,
                    'total' => $item->amount,
                    'frequency' => $item->subscription->billingType->name,
                    'plan_id' => $item->plans->id,
                    'start_date' => $item->subscription->start_date,
                    'end_date' => $item->subscription->end_date,
                ];

                return $item;
            });
        } else {
            // $subscription = Subscriptions::with('plans','billingType')->where('status',1)->orderby('subscriptions.id', 'ASC')->first();

            // Data insert through seeders
            // database/seeders/SubscriptionsSeeder.php
            $this->checksubscription();
            $subscriptions = Subscriptions::whereHas('plans')->with('plans', 'billingType')->where('status', 1);
            $profiledata = CompanyProfile::where('id', 1)->first();
            if (isset($profiledata->is_flat) && $profiledata->is_flat == 1) {
                $subscriptions = $subscriptions->where('flat_subscription', 1);
                if (config('app.domain_name') == 'aveyo' || config('app.domain_name') == 'aveyo2') {
                    $subscriptions = $subscriptions->orWhere('plan_id', 2)->where('status', 1);
                }
            } else {
                $subscriptions = $subscriptions->where('flat_subscription', 0);
            }
            $subscriptions = $subscriptions->orderby('subscriptions.id', 'ASC')->get();

            $data = [];
            foreach ($subscriptions as $subscription) {
                if (! empty($subscription) && $subscription != null) {
                    $frequency = isset($subscription->billingType) ? $subscription->billingType->name : null;
                    $plan = isset($subscription->plans) ? $subscription->plans->name : null;
                    $plan_id = isset($subscription->plans) ? $subscription->plans->id : null;
                    $product = isset($subscription->plans) ? $subscription->plans->product_name : null;
                    $plan_type = isset($subscription->billingType) ? $subscription->billingType->name : null;
                    $unique_pid_count = $m2_count = $pid_total_amount = $m2_total_amount = $sales_tax_amount = $total = $grand_total = $unique_pid_rack_price = $unique_pid_discount_price = $m2_rack_price = $m2_discount_price = 0;

                    $subscription_end_date = $subscription->end_date;
                    $subscription_start_date = $subscription->start_date;

                    $permars_array = [];
                    $permars_array['subscription_end_date'] = $subscription_end_date;
                    $permars_array['subscription_start_date'] = $subscription_start_date;
                    if ($plan_id == 1) {
                        $unique_pids_m2_date_datas = SalesInvoiceDetail::unique_pids_m2_date_datas($permars_array);

                        // print_r($unique_pids_m2_date_datas);
                        $unique_pid_data = $unique_pids_m2_date_datas['unique_pid_data'];
                        $m2_date_data = $unique_pids_m2_date_datas['m2_date_data'];

                        $unique_pid_count = count($unique_pid_data);
                        $pid_kw_sum = round(array_sum(array_column($unique_pid_data, 'kw')), 2);

                        $m2_count = count($m2_date_data);
                        $m2_kw_sum = round(array_sum(array_column($m2_date_data, 'kw')), 2);

                        // calculation
                        $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                        $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                        $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                        $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

                        $kw_adjusted_total_price = 0;
                        $kw_adjusted_total_count = 0;
                        if (isset($unique_pids_m2_date_datas['kw_adjusted_data']) && ! empty($unique_pids_m2_date_datas['kw_adjusted_data'])) {
                            $kw_adjusted_data = $unique_pids_m2_date_datas['kw_adjusted_data'];
                            $kw_adjusted_total_count = count($kw_adjusted_data);
                            $kw_adjusted_sum = round(array_sum(array_column($kw_adjusted_data, 'kw_diff')), 2);
                            $kw_adjusted_total_price = $kw_adjusted_sum * 1000 * $m2_discount_price;
                        }

                        $pid_total_amount = $pid_kw_sum * 1000 * $unique_pid_discount_price;
                        $m2_total_amount = $m2_kw_sum * 1000 * $m2_discount_price;
                        $total = $pid_total_amount + $m2_total_amount + $kw_adjusted_total_price;

                        $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                        $sales_tax_amount = (($total * $sales_tax_per) / 100);
                        $grand_total = ($total + $sales_tax_amount);
                    } elseif ($plan_id == 3) {
                        $permars_array['subscription_start_date'] = $subscription->start_date;
                        $everee_billing = SalesInvoiceDetail::everee_billing($permars_array);

                        $everee_payroll_histry = $everee_billing['payroll_histry_id_data'];
                        $one_time_payment_via_everee = $everee_billing['one_time_payment_date_data'];

                        $unique_pid_count = count($everee_payroll_histry);
                        $m2_count = count($one_time_payment_via_everee);

                        // calculation
                        $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                        $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                        $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                        $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

                        $pid_total_amount = ($unique_pid_count * $unique_pid_discount_price);
                        $m2_total_amount = ($m2_count * $m2_discount_price);
                        $total = ($pid_total_amount + $m2_total_amount);

                        $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                        $sales_tax_amount = (($total * $sales_tax_per) / 100);
                        $grand_total = ($total + $sales_tax_amount);
                    } elseif ($plan_id == 2) { // S-Clearance

                        $crms = Crms::find(5);
                        if ($crms && $crms->status == 0) {
                            // dd('asd');
                            continue;
                        }

                        $total = 0;
                        $totalData = $this->SClearanceData($subscription);
                        if (is_array($totalData)) {
                            $total = $totalData['total'];
                        }
                        $unique_pid_count = 0;
                        $m2_count = 0;
                        $unique_pid_rack_price = 0;
                        $unique_pid_discount_price = 0;
                        $m2_rack_price = 0;
                        $m2_discount_price = 0;
                        $pid_total_amount = 0;
                        $m2_total_amount = 0;
                        $sales_tax_per = 0;
                        $sales_tax_amount = 0;
                        $grand_total = 0;
                    } elseif ($plan_id == 5) {
                        $permars_array['subscription_start_date'] = $subscription->start_date;
                        $everee_billing = $this->userWiseBillingData($subscription);

                        $everee_payroll_histry = $everee_billing['user_id_data'];
                        $one_time_payment_via_everee = [];

                        $unique_pid_count = count($everee_payroll_histry);

                        $m2_count = count($one_time_payment_via_everee);

                        // calculation
                        $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                        $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                        $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                        $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

                        $pid_total_amount = ($unique_pid_count * $unique_pid_discount_price);
                        $m2_total_amount = ($m2_count * $m2_discount_price);
                        $total = ($pid_total_amount + $m2_total_amount);

                        $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                        $sales_tax_amount = (($total * $sales_tax_per) / 100);
                        $grand_total = ($total + $sales_tax_amount);
                    } elseif ($plan_id == 4) {
                        // SequiAI
                        $crms = Crms::find(6);
                        if ($crms && $crms->status == 0) {
                            // dd('asd');
                            continue;
                        }
                        $total = 0;
                        $totalData = $this->SequiAiData($subscription);
                        // dd($totalData);
                        if (is_array($totalData)) {
                            $total = $totalData['total'] ?? 0;
                        }
                        $unique_pid_count = 0;
                        $m2_count = 0;
                        $unique_pid_rack_price = 0;
                        $unique_pid_discount_price = 0;
                        $m2_rack_price = 0;
                        $m2_discount_price = 0;
                        $pid_total_amount = 0;
                        $m2_total_amount = 0;
                        $sales_tax_per = 0;
                        $sales_tax_amount = 0;
                        $grand_total = 0;
                    } elseif ($plan_id == 7) { // sequiArena
                        $planData = Plans::where('id', $plan_id)->first();
                        if ($planData) {
                            $startDate = $subscription->start_date;
                            $endDate = $subscription->end_date;
                            $perjobamount = $planData->unique_pid_rack_price;

                            $totaljob = User::whereBetween('created_at', [$startDate, $endDate])
                                ->count();

                            $sales_tax_per = isset($subscription->sales_tax_per) ? $subscription->sales_tax_per : 0;
                            $total = $perjobamount * $totaljob;
                            $sales_tax_amount = ($total * $sales_tax_per) / 100;
                            $grand_total = $total + $sales_tax_amount;
                            $unique_pid_count = $totaljob;

                            if ($subscription->total_pid != $totaljob) {
                                $subscriptionData = [
                                    'total_pid' => $totaljob,
                                    'amount' => $total,
                                    'grand_total' => $grand_total,
                                ];
                                Subscriptions::where('id', $subscription->id)->update($subscriptionData);
                            }
                        }
                    } else {
                        $sales_tax_per = isset($subscription->sales_tax_per) ? $subscription->sales_tax_per : 0;
                        $total = isset($subscription->amount) ? $subscription->amount : 0;
                        $sales_tax_amount = (($total * $sales_tax_per) / 100);
                        $grand_total = ($total + $sales_tax_amount);
                        $unique_pid_count = isset($subscription->total_pid) ? $subscription->total_pid : 0;

                    }

                    $data[] = [
                        'id' => $subscription->id,
                        'subscription_id' => $subscription->id,
                        'product' => $product,
                        'plan' => $plan,
                        'plan_id' => $plan_id,
                        'plan_type' => $plan_type,
                        'frequency' => $frequency,
                        'start_date' => $subscription->start_date,
                        'end_date' => $subscription->end_date,
                        'unique_pid_count' => $unique_pid_count,
                        'm2_count' => $m2_count,
                        'pid_total_amount' => round($pid_total_amount, 2),
                        'm2_total_amount' => round($m2_total_amount, 2),
                        'sales_tax_amount' => round($sales_tax_amount, 2),
                        'amount' => round($grand_total, 2),
                        'total' => round($total, 2),
                        'grand_total' => round($grand_total, 2),
                        'amount_without_tax' => round($total, 2),
                        'amount_with_tax' => round($grand_total, 2),
                        'unique_pid_rack_price' => $unique_pid_rack_price,
                        'unique_pid_discount_price' => $unique_pid_discount_price,
                        'm2_rack_price' => $m2_rack_price,
                        'm2_discount_price' => $m2_discount_price,
                        'kw_adjusted_total_count' => $kw_adjusted_total_count ?? 0,
                        'kw_adjusted_total_price' => round(($kw_adjusted_total_price ?? 0), 2),
                        'created_at' => $subscription->created_at,
                        'updated_at' => $subscription->updated_at,
                    ];
                }
            }
        }

        return response()->json([
            'ApiName' => 'get_subscriptions',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    // manageSubscription
    public function manageSubscription(Request $request)
    {
        $data = [];
        $msg = 'Success';
        $total = 0;
        $m2_total_price = 0;
        $pid_total_price = 0;
        $kw_adjusted_total_price = 0;
        $kw_adjusted_total_count = 0;
        if (! empty($request->subscription_history_id) && ($request->subscription_history_id != 0)) {
            $data = SubscriptionBillingHistory::with([
                'plans', 'subscription' => function ($subscription_qry) {
                    $subscription_qry->select('id', 'start_date', 'end_date', 'total_pid as total_payroll', 'total_m2 as total_onetime_payment', 'plan_type_id', 'status');
                }, 'SalesInvoiceDetail' => function ($SalesInvoiceDetail) {
                    $SalesInvoiceDetail->select('id', 'billing_history_id', 'kw', 'pid', 'customer_name', 'invoice_for', 'is_kw_adjusted_invoice', 'invoice_generated_on_kw');
                }, 'subscription.billingType']
            )->where('id', $request->subscription_history_id)->first();

            foreach ($data->SalesInvoiceDetail as $key => $value) {
                if ($value->invoice_for == 'unique_pid') {
                    $pid_total_price += $value->kw * 1000 * $data->plans->unique_pid_discount_price;
                } elseif ($value->invoice_for == 'm2_date') {
                    if ($value->is_kw_adjusted_invoice == 1) {
                        $kw_adjusted_total_price += $value->invoice_generated_on_kw * 1000 * $data->plans->m2_discount_price;
                        $kw_adjusted_total_count++;
                    } else {
                        $m2_total_price += $value->kw * 1000 * $data->plans->m2_discount_price;
                    }
                } elseif ($value->invoice_for == 'payroll_histry') {
                    $pid_total_price += $data->plans->unique_pid_discount_price;
                } elseif ($value->invoice_for == 'one_time_paymment') {
                    $m2_total_price += $data->plans->m2_discount_price;
                }
            }

            $sequiAiData = [];
            if ($data) {
                $subscriptionData = $data;
                if ($data->plans->id == 4) {
                    $sequiAiPlanCrmSetting = CrmSetting::where('crm_id', 6)->first();

                    $sequiai_plan_id = 0;
                    if ($sequiAiPlanCrmSetting != null) {
                        $sequiAiPlanValue = isset($sequiAiPlanCrmSetting->value) ? json_decode($sequiAiPlanCrmSetting->value, true) : [];
                        $sequiai_plan_id = isset($sequiAiPlanValue['sequiai_plan_id']) ?? 0;
                    }

                    $sequiaiPlan = SequiaiPlan::find($sequiai_plan_id);

                    $total = 0;
                    $price = 0;
                    $report_count = 0;

                    $subscription_billing_history_id = $data->id;

                    $historyIds = SequiaiRequestHistory::where('subscription_billing_history_id', $subscription_billing_history_id)->pluck('id')->toArray();

                    $min_request = (int) $sequiaiPlan->min_request;
                    $min_request_price = (float) $sequiaiPlan->price;

                    $records = count($historyIds) / $min_request;
                    $records_roundup = (int) $records;
                    if (is_float($records)) {
                        $records_roundup++;
                    }
                    if ($records_roundup != 0) {
                        $total = $records_roundup * $min_request_price;
                    } else {
                        $total = $min_request_price;
                    }

                    $response = [
                        'price' => $min_request_price,
                        'report_count' => $records_roundup,
                        'total' => $total,
                    ];

                    $price = $min_request_price;
                    $report_count = $records_roundup;

                    $additioonal_credits = 0;
                    if ($price) {
                    }

                    $sequiAiData = [
                        'first_credits' => $price,
                        'additioonal_credits' => $total - $price,
                        'price_estimates' => $total,
                    ];
                }
                $data = [
                    'plan' => $data->plans->name,
                    'status' => $data->subscription->status,
                    'start_date' => $data->subscription->start_date,
                    'pid_singleprice' => $data->plans->unique_pid_discount_price,
                    'm2_singlprice' => $data->plans->m2_discount_price,
                    'frequency' => $data->subscription->billingType->name,
                    'pid_total_count' => $data->subscription->total_payroll,
                    'pid_total_price' => round($pid_total_price, 2),
                    'm2_total_count' => $data->subscription->total_onetime_payment,
                    'm2_total_price' => round($m2_total_price, 2),
                    'kw_adjusted_total_count' => $kw_adjusted_total_count ?? 0,
                    'kw_adjusted_total_price' => round(($kw_adjusted_total_price ?? 0), 2),
                    'end_date' => $data->subscription->end_date,
                    'total' => $data->amount,
                    'product' => $data->plans->product_name,
                    'unique_pid_rack_price' => $data->plans->unique_pid_rack_price,
                    'm2_rack_price' => $data->plans->m2_rack_price,
                    'sequiAiData' => $sequiAiData,
                ];

                if ($request->plan_id == 2) { // S-Clearance
                    $total = 0;
                    $price = 0;
                    $report_count = 0;
                    $totalData = $this->SClearanceData($subscriptionData, 'history');
                    if (is_array($totalData)) {
                        $data['report_count'] = $totalData['report_count'];
                        $data['pid_total_count'] = $totalData['report_count'];
                    }

                }
            }
            // $data->transform(function ($item) use ($m2_total_price,$pid_total_price ) {
            //     return [
            //         'plan' => $item->plans->name,
            //         'status' => $item->status,
            //         'start_date' => $item->subscription->start_date,
            //         'pid_singleprice' => $item->subscription->plans->unique_pid_discount_price,
            //         'm2_singlprice' => $item->subscription->plans->m2_discount_price,
            //         'frequency' => $item->subscription->billingType->frequency,
            //         'pid_total_count' => $item->subscription->total_payroll,
            //         'pid_total_price' => $pid_total_price,
            //         'm2_total_count' => $item->subscription->total_onetime_payment,
            //         'm2_total_price' => $m2_total_price,
            //         'end_date' => $item->subscription->end_date,
            //         'total' => $item->amount,
            //         'product' => $item->plans->product_name,
            //         'unique_pid_rack_price' => $item->subscription->unique_pid_rack_price,
            //         'm2_rack_price' => $item->subscription->m2_rack_price,
            //     ];
            // });

        } elseif (! empty($request->subscription_id) && ($request->subscription_id != 0)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $request->subscription_id)->first();
            if (! empty($subscription)) {

                $frequency = isset($subscription->billingType) ? $subscription->billingType->name : null;
                $plan = isset($subscription->plans) ? $subscription->plans->name : null;
                $plan_id = isset($subscription->plans) ? $subscription->plans->id : null;
                $product = isset($subscription->plans) ? $subscription->plans->product_name : null;
                $plan_type = isset($subscription->billingType) ? $subscription->billingType->name : null;
                $unique_pid_count = $m2_count = $pid_total_amount = $m2_total_amount = $sales_tax_amount = $total = $grand_total = $unique_pid_rack_price = $unique_pid_discount_price = $m2_rack_price = $m2_discount_price = 0;

                $subscription_end_date = $subscription->end_date;
                $subscription_start_date = $subscription->start_date;

                $permars_array = [];
                $permars_array['subscription_end_date'] = $subscription_end_date;
                $permars_array['subscription_start_date'] = $subscription_start_date;

                $sequiAiData = [];
                if ($request->plan_id == 1) {
                    $unique_pids_m2_date_datas = SalesInvoiceDetail::unique_pids_m2_date_datas($permars_array);

                    $unique_pid_data = $unique_pids_m2_date_datas['unique_pid_data'];
                    $m2_date_data = $unique_pids_m2_date_datas['m2_date_data'];

                    $unique_pid_count = count($unique_pid_data);
                    $pid_kw_sum = round(array_sum(array_column($unique_pid_data, 'kw')), 2);

                    $m2_count = count($m2_date_data);
                    $m2_kw_sum = round(array_sum(array_column($m2_date_data, 'kw')), 2);

                    // calculation
                    $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                    $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                    $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                    $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;
                    $kw_adjusted_total_price = 0;
                    $kw_adjusted_total_count = 0;
                    if (isset($unique_pids_m2_date_datas['kw_adjusted_data']) && ! empty($unique_pids_m2_date_datas['kw_adjusted_data'])) {
                        $kw_adjusted_data = $unique_pids_m2_date_datas['kw_adjusted_data'];
                        $kw_adjusted_total_count = count($kw_adjusted_data);
                        $kw_adjusted_sum = round(array_sum(array_column($kw_adjusted_data, 'kw_diff')), 2);
                        $kw_adjusted_total_price = $kw_adjusted_sum * 1000 * $m2_discount_price;
                    }

                    $pid_total_amount = ($pid_kw_sum * 1000 * $unique_pid_discount_price);
                    $m2_total_amount = ($m2_kw_sum * 1000 * $m2_discount_price);
                    $total = ($pid_total_amount + $m2_total_amount + $kw_adjusted_total_price);

                    $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                    $sales_tax_amount = (($total * $sales_tax_per) / 100);
                    $grand_total = ($total + $sales_tax_amount);

                } elseif ($request->plan_id == 3) {
                    $permars_array['subscription_start_date'] = $subscription->start_date;
                    $everee_billing = SalesInvoiceDetail::everee_billing($permars_array);

                    $everee_payroll_histry = $everee_billing['payroll_histry_id_data'];
                    $one_time_payment_via_everee = $everee_billing['one_time_payment_date_data'];

                    $unique_pid_count = count($everee_payroll_histry);
                    $m2_count = count($one_time_payment_via_everee);

                    // calculation
                    $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                    $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                    $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                    $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

                    $pid_total_amount = ($unique_pid_count * $unique_pid_discount_price);
                    $m2_total_amount = ($m2_count * $m2_discount_price);
                    $total = ($pid_total_amount + $m2_total_amount);

                    $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                    $sales_tax_amount = (($total * $sales_tax_per) / 100);
                    $grand_total = ($total + $sales_tax_amount);
                } elseif ($plan_id == 2) { // S-Clearance
                    $total = 0;
                    $price = 0;
                    $report_count = 0;
                    $totalData = $this->SClearanceData($subscription);
                    if (is_array($totalData)) {
                        $total = $totalData['total'];
                        $price = $totalData['price'];
                        $report_count = $totalData['report_count'];
                    }
                    $unique_pid_count = 0;
                    $m2_count = 0;
                    $unique_pid_rack_price = 0;
                    $unique_pid_discount_price = 0;
                    $m2_rack_price = 0;
                    $m2_discount_price = 0;
                    $pid_total_amount = 0;
                    $m2_total_amount = 0;
                    $sales_tax_per = 0;
                    $sales_tax_amount = 0;
                    $grand_total = 0;
                } elseif ($request->plan_id == 5) {
                    $permars_array['subscription_start_date'] = $subscription->start_date;
                    // $everee_billing = SalesInvoiceDetail::everee_billing($permars_array);
                    $totalData = $this->userWiseBillingData($subscription);

                    $userData_histry = $totalData['user_id_data'];
                    // $one_time_payment_via_everee = $user_billing['one_time_payment_date_data'];

                    $unique_pid_count = count($userData_histry);
                    // $m2_count = count($one_time_payment_via_everee);

                    // calculation
                    $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                    $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                    $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                    $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

                    $pid_total_amount = ($unique_pid_count * $unique_pid_discount_price);
                    $m2_total_amount = ($m2_count * $m2_discount_price);
                    $total = ($pid_total_amount + $m2_total_amount);

                    $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                    $sales_tax_amount = (($total * $sales_tax_per) / 100);
                    $grand_total = ($total + $sales_tax_amount);
                } elseif ($request->plan_id == 4) {
                    $total = 0;
                    $price = 0;
                    $report_count = 0;

                    $totalData = $this->SequiAiData($subscription);
                    // dd($totalData);
                    if (is_array($totalData)) {
                        $total = $totalData['total'] ?? 0;
                        $price = $totalData['price'] ?? 0;
                        $report_count = $totalData['report_count'] ?? 0;

                        $additioonal_credits = 0;
                        if ($price) {

                        }

                        $sequiAiData = [
                            'first_credits' => $price,
                            'additioonal_credits' => $total - $price,
                            'price_estimates' => $total,
                        ];
                    }
                    $unique_pid_count = 0;
                    $m2_count = 0;
                    $unique_pid_rack_price = 0;
                    $unique_pid_discount_price = 0;
                    $m2_rack_price = 0;
                    $m2_discount_price = 0;
                    $pid_total_amount = 0;
                    $m2_total_amount = 0;
                    $sales_tax_per = 0;
                    $sales_tax_amount = 0;
                    $grand_total = 0;
                } else {
                    $sales_tax_per = isset($subscription->sales_tax_per) ? $subscription->sales_tax_per : 0;
                    $total = isset($subscription->amount) ? $subscription->amount : 0;
                    $sales_tax_amount = (($total * $sales_tax_per) / 100);
                    $grand_total = ($total + $sales_tax_amount);
                    $unique_pid_count = isset($subscription->total_pid) ? $subscription->total_pid : 0;
                    $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                }

                $data = [
                    'plan' => $plan,
                    'plan_id' => $plan_id,
                    'plan_type' => $plan_type,
                    'product' => $product,
                    'status' => $subscription->status,
                    'frequency' => $frequency,
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                    'pid_singleprice' => $unique_pid_discount_price,
                    'm2_singlprice' => $m2_discount_price,

                    'unique_pid_rack_price' => $unique_pid_rack_price,
                    'unique_pid_discount_price' => $unique_pid_discount_price,
                    'm2_rack_price' => $m2_rack_price,
                    'm2_discount_price' => $m2_discount_price,

                    'pid_total_count' => $unique_pid_count,
                    'm2_total_count' => $m2_count,
                    'pid_total_price' => round($pid_total_amount, 2),
                    'm2_total_price' => round($m2_total_amount, 2),
                    'kw_adjusted_total_count' => $kw_adjusted_total_count ?? 0,
                    'kw_adjusted_total_price' => round(($kw_adjusted_total_price ?? 0), 2),
                    'price_estimate' => round($grand_total, 2),
                    'sales_tax_amount' => round($sales_tax_amount, 2),
                    'price_estimate_with_tax' => round($grand_total, 2),
                    'price_estimate_without_tax' => round($total, 2),
                    'total' => round($total, 2),
                    'grand_total' => round($grand_total, 2),
                    'report_count' => @$report_count,
                    'price' => @$price,
                    'sequiAiData' => $sequiAiData,
                ];
            }

            return response()->json([
                'ApiName' => 'manage_subscription',
                'status' => true,
                'message' => $msg,
                'data' => $data,

            ], 200);
        }

        return response()->json([
            'ApiName' => 'manage_subscription',
            'status' => true,
            'message' => $msg,
            'data' => $data,
        ], 200);

    }

    // get Pids data
    public function getPidsdata(Request $request)
    {
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $unique_pid_rack_price = $unique_pid_discount_price = $m2_rack_price = $m2_discount_price = $pid_count = 0;
        $total_price = $sales_tax_amount = $sales_tax_per = $grand_total = $pid_kw_sum = 0;
        $pricebilled = [];
        $getdata = [];
        $status_code = 400;
        $status = false;
        $message = 'subscription not found!';
        if (! empty($request->subscription_id) && ($request->subscription_id != 0)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $request->subscription_id)->first();
            // Seqiucrm subscription pid list
            if (isset($subscription->plans) && $subscription->plans->id == 6) {
                return Buckets::getpidstotals($request, $subscription->id);
            }
            if (! empty($subscription) && $subscription != null) {
                $status_code = 200;
                $status = true;
                $message = 'Data get!';

                $subscription_end_date = $subscription->end_date;
                $subscription_start_date = $subscription->start_date;

                $permars_array = [];
                $permars_array['subscription_end_date'] = $subscription_end_date;
                $permars_array['subscription_start_date'] = $subscription_start_date;

                $unique_pids_m2_date_datas = SalesInvoiceDetail::unique_pids_m2_date_datas($permars_array);

                $unique_pid_data = $unique_pids_m2_date_datas['unique_pid_data'];
                $m2_date_data = $unique_pids_m2_date_datas['m2_date_data'];

                $pid_count = count($unique_pid_data);
                $pid_kw_sum = round(array_sum(array_column($unique_pid_data, 'kw')), 2);

                // calculation
                $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

                $pid_total_amount = $pid_kw_sum * 1000 * $unique_pid_discount_price;
                $total_price = $pid_total_amount;

                $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                $sales_tax_amount = (($total_price * $sales_tax_per) / 100);
                $grand_total = ($total_price + $sales_tax_amount);

                foreach ($unique_pid_data as $data) {
                    $billed_price = ($unique_pid_discount_price * $data['kw']) * 1000;
                    $getdata[] = [
                        'id' => $data['id'],
                        'pid' => $data['pid'],
                        'customer_name' => $data['customer_name'],
                        'customer_state' => $data['customer_state'],
                        'data_from' => $data['data_from'],
                        'kw' => $data['kw'],
                        'm2_date' => $data['m2_date'],
                        'approval_date' => $data['customer_signoff'],
                        'price' => $unique_pid_discount_price,
                        'billed_price' => round($billed_price, 2),
                    ];
                }
            }
        }

        $getdata = paginate($getdata, $perpage);

        return response()->json([
            'ApiName' => 'get_pidsdata',
            'status' => $status,
            'message' => $message,
            'pid_total' => $pid_count,
            'kw_total' => round($pid_kw_sum, 2),
            'total_price' => round($total_price, 2),
            'sales_tax_amount' => round($sales_tax_amount, 2),
            'sales_tax_per' => $sales_tax_per,
            'total_price_without_tex' => round($total_price, 2),
            'total_price_with_tex' => round($grand_total, 2),
            'unique_pid_rack_price' => $unique_pid_rack_price,
            'unique_pid_discount_price' => $unique_pid_discount_price,
            'm2_rack_price' => $m2_rack_price,
            'm2_discount_price' => $m2_discount_price,
            'data' => $getdata,
        ], $status_code);
    }

    // get m2 date data
    public function getm2data(Request $request): JsonResponse
    {
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $total_m2 = '';
        $m2_kw_sum = '';
        $unique_pid_rack_price = $unique_pid_discount_price = $m2_rack_price = $m2_discount_price = $pid_count = 0;
        $pricebilled = [];
        $getdata = [];
        $status_code = 400;
        $status = false;
        $message = 'subscription not found!';
        $total_price = $sales_tax_amount = $sales_tax_per = $grand_total = $pid_kw_sum = 0;
        if (! empty($request->subscription_id) && ($request->subscription_id != 0)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $request->subscription_id)->first();
            if (! empty($subscription)) {
                $status_code = 200;
                $status = true;
                $message = 'Data get!';

                $subscription_end_date = $subscription->end_date;
                $subscription_start_date = $subscription->start_date;

                $permars_array = [];
                $permars_array['subscription_end_date'] = $subscription_end_date;
                $permars_array['subscription_start_date'] = $subscription_start_date;

                $unique_pids_m2_date_datas = SalesInvoiceDetail::unique_pids_m2_date_datas($permars_array);

                $m2_date_data = $unique_pids_m2_date_datas['m2_date_data'];

                $total_m2 = count($m2_date_data);
                $m2_kw_sum = round(array_sum(array_column($m2_date_data, 'kw')), 2);

                // calculation
                $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

                $m2_total_amount = (float) ($m2_kw_sum * 1000 * $m2_discount_price);
                $total_price = (float) ($m2_total_amount);

                $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                $sales_tax_amount = (float) (($total_price * $sales_tax_per) / 100);
                $grand_total = (float) ($total_price + $sales_tax_amount);

                foreach ($m2_date_data as $data) {
                    $billed_price = ($m2_discount_price * $data['kw']) * 1000;
                    $getdata[] = [
                        'id' => $data['id'],
                        'pid' => $data['pid'],
                        'customer_name' => $data['customer_name'],
                        'customer_state' => $data['customer_state'],
                        'data_from' => $data['data_from'],
                        'kw' => $data['kw'],
                        'm2_date' => $data['m2_date'],
                        'approval_date' => $data['customer_signoff'],
                        'price' => $m2_discount_price,
                        'billed_price' => round($billed_price, 2),
                    ];
                }
            }
        }

        $getdata = paginate($getdata, $perpage);

        return response()->json([
            'ApiName' => 'get_m2data',
            'status' => $status,
            'message' => $message,
            'm2_total' => $total_m2,
            'kw_total' => $m2_kw_sum,
            'total_price' => round($total_price, 2),
            'sales_tax_amount' => round($sales_tax_amount, 2),
            'sales_tax_per' => $sales_tax_per,
            'total_price_without_tex' => round($total_price, 2),
            'total_price_with_tex' => round($grand_total, 2),
            'unique_pid_rack_price' => $unique_pid_rack_price,
            'unique_pid_discount_price' => $unique_pid_discount_price,
            'm2_rack_price' => $m2_rack_price,
            'm2_discount_price' => $m2_discount_price,
            'data' => $getdata,
        ], $status_code);

    }

    // get payroll histry data
    public function getPayrolldata(Request $request): JsonResponse
    {
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $unique_pid_rack_price = $unique_pid_discount_price = $m2_rack_price = $m2_discount_price = $pid_count = 0;
        $total_price = $sales_tax_amount = $sales_tax_per = $grand_total = $pid_kw_sum = 0;
        $pricebilled = [];
        $getdata = [];
        $status_code = 400;
        $status = false;
        $message = 'subscription not found!';
        if (! empty($request->subscription_id) && ($request->subscription_id != 0)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $request->subscription_id)->where('plan_id', $request->plan_id)->first();

            if (! empty($subscription) && $subscription != null) {
                $status_code = 200;
                $status = true;
                $message = 'Data get!';

                $subscription_end_date = $subscription->end_date;

                $permars_array = [];
                $permars_array['subscription_end_date'] = $subscription_end_date;
                $permars_array['subscription_start_date'] = $subscription->start_date;

                $everee_billing = SalesInvoiceDetail::everee_billing($permars_array);

                $everee_payroll_histry = $everee_billing['payroll_histry_id_data'];
                $payroll_histry_count = count($everee_payroll_histry);

                // calculation
                $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

                $payroll_total_amount = $payroll_histry_count * $unique_pid_discount_price;
                $total_price = $payroll_total_amount;

                $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                $sales_tax_amount = (($total_price * $sales_tax_per) / 100);
                $grand_total = ($total_price + $sales_tax_amount);
                $payment_total = 0;
                foreach ($everee_payroll_histry as $data) {
                    $billed_price = $unique_pid_discount_price;
                    $payment_total += $data['net_pay'];
                    $getdata[] = [
                        'id' => $data['id'],
                        'background_check_type' => null,
                        'check_id' => $data['everee_payment_requestId'],
                        'check_date' => $data['created_at'],
                        'user_name' => $data['usersdata']['first_name'].' '.$data['usersdata']['last_name'],
                        'user_email' => $data['usersdata']['email'],
                        'payment_ammount' => $data['net_pay'],
                        'billed_price' => round($billed_price, 2),
                    ];
                }
            }
        }
        $getdata = paginate($getdata, $perpage);

        return response()->json([
            'ApiName' => 'get payroll data',
            'status' => $status,
            'message' => $message,
            'check_total' => $payroll_histry_count,
            // 'kw_total'=>round($pid_kw_sum, 2),
            'total_price' => round($total_price, 2),
            'payment_total' => round($payment_total, 2),
            'sales_tax_amount' => round($sales_tax_amount, 2),
            'sales_tax_per' => $sales_tax_per,
            'total_price_without_tex' => round($total_price, 2),
            'total_price_with_tex' => round($grand_total, 2),
            'unique_pid_rack_price' => $unique_pid_rack_price,
            'unique_pid_discount_price' => $unique_pid_discount_price,
            'm2_rack_price' => $m2_rack_price,
            'm2_discount_price' => $m2_discount_price,
            'data' => $getdata,
        ], $status_code);
    }

    // get one time payment data
    public function getOneTimePaymentData(Request $request): JsonResponse
    {
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $total_m2 = '';
        $m2_kw_sum = '';
        $unique_pid_rack_price = $unique_pid_discount_price = $m2_rack_price = $m2_discount_price = $pid_count = 0;
        $pricebilled = [];
        $getdata = [];
        $status_code = 400;
        $status = false;
        $message = 'subscription not found!';
        $total_price = $sales_tax_amount = $sales_tax_per = $grand_total = $pid_kw_sum = 0;
        if (! empty($request->subscription_id) && ($request->subscription_id != 0)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $request->subscription_id)->where('plan_id', $request->plan_id)->first();
            if (! empty($subscription)) {
                $status_code = 200;
                $status = true;
                $message = 'Data get!';

                $subscription_end_date = $subscription->end_date;

                $permars_array = [];
                $permars_array['subscription_end_date'] = $subscription_end_date;
                $permars_array['subscription_start_date'] = $subscription->start_date;

                $everee_billing = SalesInvoiceDetail::everee_billing($permars_array);

                $one_time_payment_via_everee = $everee_billing['one_time_payment_date_data'];

                $total_onetime_payment = count($one_time_payment_via_everee);

                // calculation
                $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

                $onetime_payment_total_amount = (float) ($total_onetime_payment * $m2_discount_price);
                $total_price = (float) ($onetime_payment_total_amount);

                $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                $sales_tax_amount = (float) (($total_price * $sales_tax_per) / 100);
                $grand_total = (float) ($total_price + $sales_tax_amount);
                $payment_total = 0;
                foreach ($one_time_payment_via_everee as $data) {
                    $billed_price = $unique_pid_rack_price;
                    $payment_total += $data['net_pay'];
                    $getdata[] = [
                        'id' => $data['id'],
                        'background_check_type' => null,
                        'check_id' => $data['everee_payment_requestId'],
                        'check_date' => $data['created_at'],
                        'user_name' => $data['usersdata']['first_name'].' '.$data['usersdata']['last_name'],
                        'user_email' => $data['usersdata']['email'],
                        'payment_ammount' => $data['net_pay'],
                        'billed_price' => round($billed_price, 2),
                    ];
                }
            }
        }

        $getdata = paginate($getdata, $perpage);

        return response()->json([
            'ApiName' => 'get one time payment data',
            'status' => $status,
            'message' => $message,
            'check_total' => $total_onetime_payment,
            // 'kw_total'=>round($pid_kw_sum, 2),
            'total_price' => round($total_price, 2),
            'payment_total' => round($payment_total, 2),
            'sales_tax_amount' => round($sales_tax_amount, 2),
            'sales_tax_per' => $sales_tax_per,
            'total_price_without_tex' => round($total_price, 2),
            'total_price_with_tex' => round($grand_total, 2),
            'unique_pid_rack_price' => $unique_pid_rack_price,
            'unique_pid_discount_price' => $unique_pid_discount_price,
            'm2_rack_price' => $m2_rack_price,
            'm2_discount_price' => $m2_discount_price,
            'data' => $getdata,
        ], $status_code);

    }

    // get_Sales_Invoice_pids for invoice details
    public function get_Payroll_Invoice_data(Request $request): JsonResponse
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $billing_history_id = $request->billing_history_id;
        $subscription_id = $request->subscription_id;

        $status_code = 400;
        $status = false;
        $message = 'No data found ';
        $data = [];
        $payment_amount = 0;
        $price_billed = 2;

        $history = SubscriptionBillingHistory::where('id', '=', $billing_history_id)->first();
        //  with(
        // [
        //     'plans' => function($plan){
        //         $plan->select('id','product_name');
        //     },'SalesInvoiceDetail' => function($SalesInvoiceDetail){
        //         $SalesInvoiceDetail->where('invoice_for', 'payroll_histry')
        //         ->select('id','billing_history_id','kw as payment_amount','pid as checkID','customer_name','data_from as check_date');
        //     },'subscription' => function($subscriptionQry){
        //         $subscriptionQry->select('id','total_pid as nos_check')
        //         ->selectRaw('total_pid * 2 as total_cost');
        //     }
        // ])
        // ->where('id','=',$billing_history_id)->first();
        // foreach ($history->SalesInvoiceDetail as $key => $value) {
        //     $payment_amount += $value->payment_amount;
        //     $value->price_billed = $price_billed;
        // }
        // if ($history) {

        //     $history->payment_amount = $payment_amount;
        //     $history->product_name = $history->plans->product_name ?? null;
        //     $history->nos_check = $history->subscription->nos_check ?? null;
        //     $history->total_cost = $history->subscription->total_cost ?? null;

        //     $status_code = 200;
        //     $status = true;
        //     $message = "Success";
        //     $data = $history;
        // }

        if (! empty($history && $history != null)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $history->subscription_id)->first();

            $status_code = 200;
            $status = true;
            $message = 'Data get sucssesfully';
            if (empty($subscription || $subscription == null)) {
                $message = 'Plan subscription deleted!';
            }

            $plan = isset($history->plan_name) ? $history->plan_name : null;
            $unique_pid_rack_price = isset($history->unique_pid_rack_price) ? $history->unique_pid_rack_price : null;
            $unique_pid_discount_price = isset($history->unique_pid_discount_price) ? $history->unique_pid_discount_price : null;
            $m2_rack_price = isset($history->m2_rack_price) ? $history->m2_rack_price : null;
            $m2_discount_price = isset($history->m2_discount_price) ? $history->m2_discount_price : null;

            $SalesInvoiceDetail_data = SalesInvoiceDetail::where('billing_history_id', $billing_history_id)->where('invoice_for', 'payroll_histry')
                ->select('id', 'billing_history_id', 'kw as payment_ammount', 'pid as check_id', 'customer_name as user_name', 'data_from as check_date');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $SalesInvoiceDetail_data->where(function ($query) use ($request) {
                    $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%');
                });
            }
            $SalesInvoiceDetail_data = $SalesInvoiceDetail_data->get();
            $kw_count = $SalesInvoiceDetail_data->count('kw');
            $kw_sum = $SalesInvoiceDetail_data->sum('kw');

            $total_amount = ($kw_count * $unique_pid_rack_price);
            $total_price = $total_amount;

            $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
            $sales_tax_amount = ($total_price * $sales_tax_per) / 100;
            $grand_total = $total_price + $sales_tax_amount;
            $payment_total = 0;
            foreach ($SalesInvoiceDetail_data as $row) {
                $billed_price = ($unique_pid_discount_price * $row->kw) * 1000;
                // $data[] = [
                //     'id' => $row->id,
                //     'pid' => $row->pid,
                //     'customer_name' => $row->customer_name,
                //     'customer_state' => $row->customer_state,
                //     'data_from' => $row->data_from,
                //     'kw' => $row->kw,
                //     'm2_date' => $row->m2_date,
                //     'approval_date' => $row->customer_signoff,
                //     'price' => $unique_pid_discount_price,
                //     'billed_price' => round($billed_price, 2),
                //     'created_at' => $row->created_at,
                //     'updated_at' => $row->updated_at
                // ];
                $billed_price = $unique_pid_rack_price;
                $payment_total += $row['payment_ammount'];
                $data[] = [
                    'id' => $row['id'],
                    'check_id' => $row['check_id'],
                    'check_date' => $row['check_date'],
                    'user_name' => $row['user_name'],
                    'payment_ammount' => $row['payment_ammount'],
                    'billed_price' => round($billed_price, 2),
                ];
            }
            // $data = paginate($data, $perpage);
            // return  response()->json([
            //     'ApiName' => 'get_Payroll_Invoice_pids',
            //     'status' => $status,
            //     'message' => $message,
            //     'pid_total_count'=>$kw_count,
            //     'pid_total'=>$kw_count,
            //     'kw_total'=>round($kw_sum, 2),
            //     'total_price'=>round($total_price, 2),
            //     'sales_tax_amount'=>round($sales_tax_amount, 2),
            //     'total_price_with_tex'=>round($grand_total, 2),
            //     'sales_tax_per'=>$sales_tax_per,
            //     'unique_pid_rack_price'=>$unique_pid_rack_price,
            //     'unique_pid_discount_price'=>$unique_pid_discount_price,
            //     'm2_rack_price'=>$m2_rack_price,
            //     'm2_discount_price'=>$m2_discount_price,
            //     'data' => $data,
            // ], $status_code);

            $data = paginate($data, $perpage);

            return response()->json([
                'ApiName' => 'get_Payroll_Invoice_data',
                'status' => $status,
                'message' => $message,
                'check_total' => $kw_count,
                // 'kw_total'=>round($pid_kw_sum, 2),
                'total_price' => round($total_price, 2),
                'payment_total' => round($payment_total, 2),
                'sales_tax_amount' => round($sales_tax_amount, 2),
                'sales_tax_per' => $sales_tax_per,
                'total_price_without_tex' => round($total_price, 2),
                'total_price_with_tex' => round($grand_total, 2),
                'unique_pid_rack_price' => $unique_pid_rack_price,
                'unique_pid_discount_price' => $unique_pid_discount_price,
                'm2_rack_price' => $m2_rack_price,
                'm2_discount_price' => $m2_discount_price,
                'data' => $data,
            ], $status_code);
        }

        return response()->json([
            'ApiName' => 'get_Payroll_Invoice_data',
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status_code);
    }

    // get_Sales_Invoice_m2_data for invoice details
    public function get_OneTimePayment_Invoice_data(Request $request): JsonResponse
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $billing_history_id = $request->billing_history_id;
        $subscription_id = $request->subscription_id;

        // $history = SubscriptionBillingHistory::where('id','=',$billing_history_id)->first();

        $status_code = 400;
        $status = false;
        $message = 'No data found!';
        $payment_amount = 0;
        $price_billed = 2;

        $data = [];
        // \DB::connection()->enableQueryLog();
        $history = SubscriptionBillingHistory::where('id', '=', $billing_history_id)->first();
        // with([
        //     'plans' => function($plan){
        //         $plan->select('id','product_name');
        //     },'SalesInvoiceDetail' => function($SalesInvoiceDetail){
        //         $SalesInvoiceDetail->where('invoice_for', 'one_time_paymment')
        //         ->select('id','billing_history_id','kw as payment_amount','pid as checkID','customer_name','data_from as check_date');
        //     },'subscription' => function($subscriptionQry){
        //         $subscriptionQry->select('id','total_m2 as nos_check')
        //         ->selectRaw('total_m2 * 2 as total_cost');
        //     }
        // ])
        // ->where('id','=',$billing_history_id)->first();

        // foreach ($history->SalesInvoiceDetail as $key => $value) {
        //     $payment_amount += $value->payment_amount;
        //     $value->price_billed = $price_billed;
        // }

        // if ($history) {
        //     // Append the values to the main $history array
        //     $history->payment_total = $payment_amount;
        //     $history->product_name = $history->plans->product_name ?? null;
        //     $history->nos_check = $history->subscription->nos_check ?? null;
        //     $history->total_cost = $history->subscription->total_cost ?? null;

        //     $status_code = 200;
        //     $status = true;
        //     $message = "Data get sucssesfully";
        //     $data = $history;
        // }

        if (! empty($history && $history != null)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $history->subscription_id)->first();

            $status_code = 200;
            $status = true;
            $message = 'Data get sucssesfully';
            if (empty($subscription || $subscription == null)) {
                $message = 'Plan subscription deleted!';
            }

            $plan = isset($history->plan_name) ? $history->plan_name : null;
            $unique_pid_rack_price = isset($history->unique_pid_rack_price) ? $history->unique_pid_rack_price : null;
            $unique_pid_discount_price = isset($history->unique_pid_discount_price) ? $history->unique_pid_discount_price : null;
            $m2_rack_price = isset($history->m2_rack_price) ? $history->m2_rack_price : null;
            $m2_discount_price = isset($history->m2_discount_price) ? $history->m2_discount_price : null;

            $datalist = SalesInvoiceDetail::where('billing_history_id', $billing_history_id)->where('invoice_for', 'one_time_paymment')
                ->select('id', 'billing_history_id', 'kw as payment_amount', 'pid as check_id', 'customer_name as user_name', 'data_from as check_date');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $datalist->where(function ($query) use ($request) {
                    $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%');
                });
            }
            $SalesInvoiceDetail_data = $datalist->get();
            $kw_count = $datalist->count('kw');
            $kw_sum = $datalist->sum('kw');

            $total_amount = ($kw_count * $m2_discount_price);
            $total_price = ($total_amount);

            $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
            $sales_tax_amount = (($total_price * $sales_tax_per) / 100);
            $grand_total = ($total_price + $sales_tax_amount);
            $payment_total = 0;
            foreach ($SalesInvoiceDetail_data as $row) {
                // $billed_price  = ($m2_discount_price * $row->kw) * 1000;
                // $data[] = [
                //     'id' => $row->id,
                //     'pid' => $row->pid,
                //     'customer_name' => $row->customer_name,
                //     'customer_state' => $row->customer_state,
                //     'data_from' => $row->data_from,
                //     'kw' => $row->kw,
                //     'm2_date' => $row->m2_date,
                //     'approval_date' => $row->customer_signoff,
                //     'price' => $m2_discount_price,
                //     'billed_price' => round($billed_price, 2),
                //     'created_at' => $row->created_at,
                //     'updated_at' => $row->updated_at
                // ];

                $billed_price = $unique_pid_rack_price;
                $payment_total += $row['payment_amount'];
                $data[] = [
                    'id' => $row['billing_history_id'],
                    'check_id' => $row['check_id'],
                    'check_date' => $row['check_date'],
                    'user_name' => $row['user_name'],
                    // 'user_email' =>  $row['usersdata']['email'] ,
                    'payment_ammount' => $row['payment_amount'],
                    'billed_price' => round($billed_price, 2),
                ];
            }
            // $data = paginate($data,$perpage);

            // return  response()->json([
            //     'ApiName' => 'get_Sales_Invoice_m2_data',
            //     'status' => $status,
            //     'message' => $message,
            //     'pid_total_count'=>$kw_count,
            //     'm2_total'=>$kw_count,
            //     'kw_total'=>round($kw_sum, 2),
            //     'total_price'=>round($total_price, 2),
            //     'sales_tax_amount'=>round($sales_tax_amount, 2),
            //     'total_price_with_tex'=>round($grand_total, 2),
            //     'sales_tax_per'=>$sales_tax_per,
            //     'unique_pid_rack_price'=>$unique_pid_rack_price,
            //     'unique_pid_discount_price'=>$unique_pid_discount_price,
            //     'm2_rack_price'=>$m2_rack_price,
            //     'm2_discount_price'=>$m2_discount_price,
            //     'data' => $data,
            // ], $status_code);
            $data = paginate($data, $perpage);

            return response()->json([
                'ApiName' => 'get_OneTimePayment_Invoice_data',
                'status' => $status,
                'message' => $message,
                'check_total' => $kw_count,
                // 'kw_total'=>round($pid_kw_sum, 2),
                'total_price' => round($total_price, 2),
                'payment_total' => round($payment_total, 2),
                'sales_tax_amount' => round($sales_tax_amount, 2),
                'sales_tax_per' => $sales_tax_per,
                'total_price_without_tex' => round($total_price, 2),
                'total_price_with_tex' => round($grand_total, 2),
                'unique_pid_rack_price' => $unique_pid_rack_price,
                'unique_pid_discount_price' => $unique_pid_discount_price,
                'm2_rack_price' => $m2_rack_price,
                'm2_discount_price' => $m2_discount_price,
                'data' => $data,
            ], $status_code);
        }

        return response()->json([
            'ApiName' => 'get_OneTimePayment_Invoice_data',
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status_code);
    }

    // get_Sales_Invoice_pids for invoice details
    public function get_Sales_Invoice_pids(Request $request): JsonResponse
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $billing_history_id = $request->billing_history_id;
        $subscription_id = $request->subscription_id;

        $history = SubscriptionBillingHistory::where('id', '=', $billing_history_id)->first();

        $status_code = 400;
        $status = false;
        $message = 'No data found!! Invaild billing history id';

        $data = [];
        if (! empty($history && $history != null)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $history->subscription_id)->first();

            $status_code = 200;
            $status = true;
            $message = 'Data get sucssesfully';
            if (empty($subscription || $subscription == null)) {
                $message = 'Plan subscription deleted!';
            }

            $plan = isset($history->plan_name) ? $history->plan_name : null;
            $unique_pid_rack_price = isset($history->unique_pid_rack_price) ? $history->unique_pid_rack_price : null;
            $unique_pid_discount_price = isset($history->unique_pid_discount_price) ? $history->unique_pid_discount_price : null;
            $m2_rack_price = isset($history->m2_rack_price) ? $history->m2_rack_price : null;
            $m2_discount_price = isset($history->m2_discount_price) ? $history->m2_discount_price : null;

            $SalesInvoiceDetail_data = SalesInvoiceDetail::where('billing_history_id', $billing_history_id)->where('invoice_for', '=', 'unique_pid');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $SalesInvoiceDetail_data->where(function ($query) use ($request) {
                    $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%');
                });
            }
            $SalesInvoiceDetail_data = $SalesInvoiceDetail_data->get();
            $kw_count = $SalesInvoiceDetail_data->count('kw');
            $kw_sum = $SalesInvoiceDetail_data->sum('kw');

            $total_amount = ($kw_sum * 1000 * $unique_pid_discount_price);
            $total_price = $total_amount;

            $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
            $sales_tax_amount = ($total_price * $sales_tax_per) / 100;
            $grand_total = $total_price + $sales_tax_amount;

            foreach ($SalesInvoiceDetail_data as $row) {
                $billed_price = ($unique_pid_discount_price * $row->kw) * 1000;
                $data[] = [
                    'id' => $row->id,
                    'pid' => $row->pid,
                    'customer_name' => $row->customer_name,
                    'customer_state' => $row->customer_state,
                    'data_from' => $row->data_from,
                    'kw' => $row->kw,
                    'm2_date' => $row->m2_date,
                    'approval_date' => $row->customer_signoff,
                    'price' => $unique_pid_discount_price,
                    'billed_price' => round($billed_price, 2),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }
            $data = paginate($data, $perpage);

            return response()->json([
                'ApiName' => 'get_Sales_Invoice_pids',
                'status' => $status,
                'message' => $message,
                'pid_total_count' => $kw_count,
                'pid_total' => $kw_count,
                'kw_total' => round($kw_sum, 2),
                'total_price' => round($total_price, 2),
                'sales_tax_amount' => round($sales_tax_amount, 2),
                'total_price_with_tex' => round($grand_total, 2),
                'sales_tax_per' => $sales_tax_per,
                'unique_pid_rack_price' => $unique_pid_rack_price,
                'unique_pid_discount_price' => $unique_pid_discount_price,
                'm2_rack_price' => $m2_rack_price,
                'm2_discount_price' => $m2_discount_price,
                'data' => $data,
            ], $status_code);
        }

        return response()->json([
            'ApiName' => 'get_Sales_Invoice_pids',
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status_code);
    }

    // get_Sales_Invoice_m2_data for invoice details
    public function get_Sales_Invoice_m2_data(Request $request)
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $billing_history_id = $request->billing_history_id;
        $subscription_id = $request->subscription_id;

        $history = SubscriptionBillingHistory::where('id', '=', $billing_history_id)->first();

        $status_code = 400;
        $status = false;
        $message = 'No data found!! Invaild billing history id';

        $data = [];
        if (! empty($history && $history != null)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $history->subscription_id)->first();

            $status_code = 200;
            $status = true;
            $message = 'Data get sucssesfully';
            if (empty($subscription || $subscription == null)) {
                $message = 'Plan subscription deleted!';
            }

            $plan = isset($history->plan_name) ? $history->plan_name : null;
            $unique_pid_rack_price = isset($history->unique_pid_rack_price) ? $history->unique_pid_rack_price : null;
            $unique_pid_discount_price = isset($history->unique_pid_discount_price) ? $history->unique_pid_discount_price : null;
            $m2_rack_price = isset($history->m2_rack_price) ? $history->m2_rack_price : null;
            $m2_discount_price = isset($history->m2_discount_price) ? $history->m2_discount_price : null;

            $datalist = SalesInvoiceDetail::where('billing_history_id', $billing_history_id)->where('invoice_for', '=', 'm2_date')->where('is_kw_adjusted_invoice', '=', '0');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $datalist->where(function ($query) use ($request) {
                    $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%');
                });
            }
            $SalesInvoiceDetail_data = $datalist->get();
            // return $SalesInvoiceDetail_data;
            $kw_count = $datalist->count('kw');
            $kw_sum = $datalist->sum('kw');

            $total_amount = ($kw_sum * 1000 * $m2_discount_price);
            $total_price = ($total_amount);

            $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
            $sales_tax_amount = (($total_price * $sales_tax_per) / 100);
            $grand_total = ($total_price + $sales_tax_amount);

            foreach ($SalesInvoiceDetail_data as $row) {
                $billed_price = ($m2_discount_price * $row->kw) * 1000;
                $data[] = [
                    'id' => $row->id,
                    'pid' => $row->pid,
                    'customer_name' => $row->customer_name,
                    'customer_state' => $row->customer_state,
                    'data_from' => $row->data_from,
                    'kw' => $row->kw,
                    'm2_date' => $row->m2_date,
                    'approval_date' => $row->customer_signoff,
                    'price' => $m2_discount_price,
                    'billed_price' => round($billed_price, 2),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }
            $data = paginate($data, $perpage);

            return response()->json([
                'ApiName' => 'get_Sales_Invoice_m2_data',
                'status' => $status,
                'message' => $message,
                'pid_total_count' => $kw_count,
                'm2_total' => $kw_count,
                'kw_total' => round($kw_sum, 2),
                'total_price' => round($total_price, 2),
                'sales_tax_amount' => round($sales_tax_amount, 2),
                'total_price_with_tex' => round($grand_total, 2),
                'sales_tax_per' => $sales_tax_per,
                'unique_pid_rack_price' => $unique_pid_rack_price,
                'unique_pid_discount_price' => $unique_pid_discount_price,
                'm2_rack_price' => $m2_rack_price,
                'm2_discount_price' => $m2_discount_price,
                'data' => $data,
            ], $status_code);
        }

        return response()->json([
            'ApiName' => 'get_Sales_Invoice_m2_data',
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status_code);
    }

    // Not in use
    public function addBilingHistory(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'subscription_id' => 'required',
                'amount' => 'required',
                'paid_status' => 'required',
                'billing_date' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        } else {
            if ($request->subscription_id != 0) {
                $invoice_no = 'S-'.rand(100000, 999999);
                $data = [
                    'subscription_id' => $request->subscription_id,
                    'amount' => $request->amount,
                    'paid_status' => $request->paid_status,
                    'invoice_no' => $invoice_no,
                    'billing_date' => $request->billing_date,
                ];
                SubscriptionBillingHistory::Create($data);
            }
        }

        return response()->json([
            'ApiName' => 'add_billinghistory',
            'status' => true,
            'message' => 'Success',
            'data' => $data,
        ], 200);
    }

    public function addpaymentdata(Request $request)
    {
        $data = [];
        $shipping = [];
        if (! empty($request->payment_id) && ($request->payment_id != 0)) {
            // $type = ($request->type == 'live') ? 'live':'test';
            $type = config('services.stripe.type', 'test');
            // ($request->type == 'live') ? 'live':'test';
            $invoice_no = $request->payment_id;
            $data = SubscriptionBillingHistory::where('invoice_no', $invoice_no)->sum('amount');
            if (! empty($data)) {

                $amounts = number_format($data, 2, '.', '');
                $amounttopay = ($amounts * 100);
                // $subscription_id = $data->subscription_id;
                $profiledata = CompanyProfile::where('id', 1)->first();
                // $stripe_customer_id = $profiledata->stripe_customer_id;

                $shipping['amount'] = $amounttopay;
                $shipping['currency'] = 'usd';
                $shipping['setup_future_usage'] = 'off_session';
                $shipping['automatic_payment_methods'] = ['enabled' => 'true'];
                // $shipping['saved_payment_method_options']=['payment_method_save' => 'enabled'];
                $shipping['metadata']['server_name'] = config('app.domain_name') ? config('app.domain_name') : config('app.env');
                $shipping['metadata']['server_url'] = config('app.base_url') ? \Config::get('app.base_url') : \Config::get('app.url');
                // $shipping['description']= "Invoice Number - Company Name | Amount | Plan Name | Plan Type";
                $shipping['description'] = $invoice_no.' - Flex Sequifi | $ '.$amounts.' | Basic(Monthly)';
                $shipping['shipping']['name'] = isset($profiledata->name) ? $profiledata->name : '';
                $BusinessAddress = BusinessAddress::first();
                $shipping['shipping']['address'] = [
                    'line1' => isset($BusinessAddress->mailing_address) ? $BusinessAddress->mailing_address : '',
                    'postal_code' => isset($BusinessAddress->mailing_zip) ? $BusinessAddress->mailing_zip : '',
                    'city' => isset($BusinessAddress->mailing_city) ? $BusinessAddress->mailing_city : '',
                    'state' => isset($BusinessAddress->mailing_state) ? $BusinessAddress->mailing_state : '',
                    'country' => isset($BusinessAddress->country) ? $BusinessAddress->country : '',
                ];
                if (empty($profiledata->stripe_customer_id) || $profiledata->stripe_customer_id === null || $profiledata->stripe_customer_id == 'NULL' || $profiledata->stripe_customer_id == 'null') {
                    $shipping['shipping']['phone'] = isset($profiledata->phone_number) ? $profiledata->phone_number : '';
                    $customerdata = $shipping['shipping'];
                    $customerdata['shipping'] = $shipping['shipping'];
                    $customerdata['email'] = isset($profiledata->company_email) ? $profiledata->company_email : '';
                    $customerdata['phone'] = isset($profiledata->phone_number) ? $profiledata->phone_number : '';
                    $customerdata['metadata']['server_name'] = config('app.domain_name') ? config('app.domain_name') : config('app.env');
                    $customerdata['metadata']['server_url'] = config('app.base_url') ? \Config::get('app.base_url') : \Config::get('app.url');
                    $stripecustomer = $this->stripecreatecustomer($customerdata, $type);
                    // print_r($stripecustomer);
                    if ($stripecustomer && $stripecustomer['id']) {
                        $shipping['customer'] = $stripecustomer['id'];
                        $pdata = ['stripe_customer_id' => $stripecustomer['id']];
                        CompanyProfile::where('id', 1)->update($pdata);
                    }
                } else {
                    $shipping['customer'] = $profiledata->stripe_customer_id;
                }

                $secretKey = $this->client_secret($shipping, $type);

                $data =
                    [
                        'client_secret' => $secretKey,
                    ];
                $update = SubscriptionBillingHistory::where('invoice_no', $invoice_no)->update($data);
                $data['billing_id'] = $invoice_no;
            }

            return $data;
        }
    }

    public static function autoallinvoicepay($invoice_no = '')
    {
        $controller = new self;
        if (empty($invoice_no)) {
            $data = SubscriptionBillingHistory::select('invoice_no')->where('paid_status', 0)->whereNull('client_secret')->groupBy('invoice_no')->get();
            Log::info('Enter '.$data);
            if ($data) {
                foreach ($data as $da) {
                    Log::info('info invoice '.$da);
                    $controller->AutoInvoicePay($da->invoice_no);
                }
            }
        } else {
            $controller->AutoInvoicePay($invoice_no);
        }
    }

    public function autopayinvoice(Request $request)
    {
        $data = [];
        $shipping = [];
        if (! empty($request->payment_id) && ($request->payment_id != 0)) {
            // $type = ($request->type == 'live') ? 'live':'test';
            $type = config('services.stripe.type', 'test');
            // ($request->type == 'live') ? 'live':'test';
            $invoice_no = $request->payment_id;
            $data = $this->AutoInvoicePay($invoice_no);

            return $data;
        } elseif (! empty($request->all) && ($request->all == 1)) {
            self::autoallinvoicepay();
        }
    }

    public static function updateinvoice()
    {
        $data = [];
        $profiledata = CompanyProfile::where('id', 1)->first();
        $type = config('services.stripe.type', 'test');
        $data = SubscriptionBillingHistory::with('subscription')
            ->wherenotnull('payment_url')
            ->wherenotnull('client_secret')
            ->get();

        foreach ($data as $da) {
            $start_date = isset($da->subscription) ? $da->subscription->start_date : '';
            $end_date = isset($da->subscription) ? $da->subscription->end_date : '';
            // Extract client secret
            $client_secret = $da->client_secret ?? '';
            // Retrieve invoice information from Stripe
            $controller = new self;
            $inv_info = $controller->Invoicesinfo($type, $client_secret);
            // Create a description for the invoice
            $desc = $da->invoice_no.' | Sequifi | '.$profiledata->name.' | '.'$'.$da->amount.' | '
                    .date('m/d/Y', strtotime($start_date)).' - '.date('m/d/Y', strtotime($end_date)).' | '.$da->plan_name;
            // Check if the payment_intent exists
            if (! empty($inv_info['payment_intent'])) {
                $pdata['id'] = $client_secret;
                $pdata['payment_intent'] = $inv_info['payment_intent'];
                $pdata['desc'] = $desc;
                try {
                    $controller = new self;
                    // Update payment intent to invoice
                    $controller->updatepaymentintenttoinvoice($type, $pdata);
                } catch (\Throwable $e) {
                    // Log the error for debugging purposes
                    \Log::error('Error updating payment intent for invoice: '.$e->getMessage());
                }
            } else {
                \Log::warning('No payment intent found for invoice: '.$da->invoice_no);
            }
        }
    }

    protected function Invoicesinfo($type, $id)
    {
        try {
            // Determine the Stripe API key based on the type (live or test)
            if ($type == 'live') {
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            // $stripe_key = 'sk_test_51NPt0DCRUyxgHsgPjs2A1FT11jfOwnEab6ReU7OuxU4jM52RBxjumxb4DP8cw9OWc0KRTx3JEunny42E32wWLIa600HmqodvJ8';

            $url = 'https://api.stripe.com/v1/invoices/'.$id;
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $pdata = [];
            $curl_response = $this->curlRequestData($url, $pdata, $headers, 'GET');
            Log::info('resp '.$curl_response);
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }
    }

    protected function updatepaymentintenttoinvoice($type, $data)
    {
        try {
            $id = $data['id'];
            $payment_intent = $data['payment_intent'];
            $desc = $data['desc'];
            // Determine the Stripe API key based on the type (live or test)
            if ($type == 'live') {
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            // $stripe_key = 'sk_test_51NPt0DCRUyxgHsgPjs2A1FT11jfOwnEab6ReU7OuxU4jM52RBxjumxb4DP8cw9OWc0KRTx3JEunny42E32wWLIa600HmqodvJ8';

            $url = 'https://api.stripe.com/v1/payment_intents/'.$payment_intent;
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $pdata = ['description' => $desc];
            $curl_response = $this->curlRequestData($url, $pdata, $headers, 'POST');
            Log::info('resp-update-desc '.$curl_response);
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }
    }

    protected function updateduedateinvoice($type, $data)
    {
        try {
            $id = $data['id'];
            $payment_intent = $data['payment_intent'];
            $desc = $data['desc'];
            // Determine the Stripe API key based on the type (live or test)
            if ($type == 'live') {
                // $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $stripe_key = 'sk_test_51NPt0DCRUyxgHsgPjs2A1FT11jfOwnEab6ReU7OuxU4jM52RBxjumxb4DP8cw9OWc0KRTx3JEunny42E32wWLIa600HmqodvJ8';

            $url = 'https://api.stripe.com/v1/invoices/'.$id;
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $twoYearsLater = strtotime(date('Y-m-d', strtotime('+2 years')));
            $pdata = ['due_date' => $twoYearsLater];
            $curl_response = $this->curlRequestData($url, $pdata, $headers, 'POST');
            Log::info('resp-update-date '.$curl_response);
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }
    }

    public static function getandupdateinv()
    {
        $profiledata = CompanyProfile::where('id', 1)->first();
        $controller = new self;
        $type = config('services.stripe.type', 'test');
        $list = $controller->stripegetinvoice($profiledata->stripe_customer_id, $type);
        foreach ($list['data'] as $li) {
            $data = SubscriptionBillingHistory::where('client_secret', $li['id'])->get();
            if (count($data) == 0) {
                $pay['payment_id'] = $li['id'];
                $controller->stripeaddinvoiceVoid($pay, $type);
            }
        }
    }

    public function listpaymentmethods(Request $request): JsonResponse
    {
        $data = $this->getpaymentlist();

        return response()->json([
            'ApiName' => 'listpaymentmethods',
            'status' => true,
            'message' => 'Success',
            'data' => $data,
        ], 200);
    }

    protected function getpaymentlist()
    {
        $profiledata = CompanyProfile::where('id', 1)->first();
        $type = config('services.stripe.type', 'test');
        $data['limit'] = 100;
        $list = $this->stripegetpaymentlist($profiledata->stripe_customer_id, $data, $type);
        $rdata = [];
        $payment_list = [];
        $fingerprints = [];
        if (isset($list['data']) && ! empty($list['data'])) {
            foreach ($list['data'] as $li) {
                $opt1 = [];
                $opt1['type'] = $li['type'];
                $opt1['payment_id'] = $li['id'];
                $opt1['icon'] = 'fa-university';
                if ($li['type'] == 'card') {
                    $opt1['brand'] = $li['card']['brand'];
                    $opt1['text'] = $li['card']['brand'].' ending in '.$li['card']['last4'];
                    $opt1['last4'] = $li['card']['last4'];
                    $opt1['exp_month'] = $li['card']['exp_month'];
                    $opt1['exp_year'] = $li['card']['exp_year'];
                    $opt1['icon'] = 'fa-cc-'.$li['card']['brand'];
                    $opt1['fingerprint'] = $li['card']['fingerprint'];
                    if (isset($li['card']['fingerprint']) && ! empty($li['card']['fingerprint']) && ! in_array($li['card']['fingerprint'], $fingerprints)) {
                        array_push($fingerprints, $li['card']['fingerprint']);
                    }
                } elseif ($li['type'] == 'link') {
                    $opt1['text'] = 'Link '.$li['link']['email'];
                    $opt1['email'] = $li['link']['email'];
                    $opt1['fingerprint'] = '';
                    // $opt1['fingerprint'] = $li['link']['fingerprint'];
                    // if(isset($li['link']['fingerprint']) && !empty($li['link']['fingerprint']) && !in_array($li['link']['fingerprint'], $fingerprints)){
                    //     array_push($fingerprints, $li['link']['fingerprint']);
                    // }
                } elseif ($li['type'] == 'bank_account') {
                    $opt1['text'] = $li['bank_account']['bank_name'].' ending in '.$li['bank_account']['last4'];
                    $opt1['last4'] = $li['bank_account']['last4'];
                    $opt1['routing_number'] = $li['bank_account']['routing_number'];
                    $opt1['fingerprint'] = $li['bank_account']['fingerprint'];
                    if (isset($li['bank_account']['fingerprint']) && ! empty($li['bank_account']['fingerprint']) && ! in_array($li['bank_account']['fingerprint'], $fingerprints)) {
                        array_push($fingerprints, $li['bank_account']['fingerprint']);
                    }
                } elseif ($li['type'] == 'us_bank_account') {
                    $opt1['text'] = $li['us_bank_account']['bank_name'].' ending in '.$li['us_bank_account']['last4'];
                    $opt1['last4'] = $li['us_bank_account']['last4'];
                    $opt1['routing_number'] = $li['us_bank_account']['routing_number'];
                    $opt1['fingerprint'] = $li['us_bank_account']['fingerprint'];
                    if (isset($li['us_bank_account']['fingerprint']) && ! empty($li['us_bank_account']['fingerprint']) && ! in_array($li['us_bank_account']['fingerprint'], $fingerprints)) {
                        array_push($fingerprints, $li['us_bank_account']['fingerprint']);
                    }
                } elseif ($li['type'] == 'sepa_debit') {
                    $opt1['text'] = $li['sepa_debit']['bank_name'].' ending in '.$li['sepa_debit']['last4'];
                    $opt1['last4'] = $li['sepa_debit']['last4'];
                    $opt1['routing_number'] = $li['sepa_debit']['routing_number'];
                    $opt1['fingerprint'] = $li['sepa_debit']['fingerprint'];
                    if (isset($li['sepa_debit']['fingerprint']) && ! empty($li['sepa_debit']['fingerprint']) && ! in_array($li['sepa_debit']['fingerprint'], $fingerprints)) {
                        array_push($fingerprints, $li['sepa_debit']['fingerprint']);
                    }
                } else {
                    $opt1['text'] = $li[$li['type']];
                }
                $payment_list[] = $opt1;
            }
        }

        $unique_payment_list = [];
        foreach ($payment_list as $record) {
            if (isset($record['fingerprint'])) {
                if (in_array($record['fingerprint'], $fingerprints)) {
                    $unique_payment_list[$record['fingerprint']] = $record; // Use fingerprint as key to ensure uniqueness
                }
            } else {
                $unique_payment_list['link'] = $record;
            }
        }

        // Convert associative array back to indexed array
        $unique_payment_list = array_values($unique_payment_list);

        $default_method = '';
        $profile = $this->stripecustomerprofile($profiledata->stripe_customer_id, $data = [], $type);
        if (isset($profile['invoice_settings']['default_payment_method'])) {
            $default_method = $profile['invoice_settings']['default_payment_method'];
        }
        $rdata['payment_list'] = $unique_payment_list;
        $rdata['default_method'] = $default_method;

        return $rdata;
    }

    public static function companyaddupdateinfo()
    {
        try {
            $profiledata = CompanyProfile::where('id', 1)->first();
            $BusinessAddress = BusinessAddress::first();
            $custadd = [];
            $custadd['name'] = isset($profiledata->name) ? $profiledata->name : '';
            $custadd['phone'] = isset($profiledata->phone_number) ? $profiledata->phone_number : '';
            $custadd['email'] = isset($profiledata->company_email) ? $profiledata->company_email : '';
            $custadd['metadata']['server_name'] = config('app.domain_name') ? config('app.domain_name') : config('app.env');
            $custadd['metadata']['server_url'] = config('app.base_url') ? \Config::get('app.base_url') : \Config::get('app.url');
            $custadd['address'] = [
                'line1' => isset($BusinessAddress->mailing_address) ? $BusinessAddress->mailing_address : '',
                'postal_code' => isset($BusinessAddress->mailing_zip) ? $BusinessAddress->mailing_zip : '',
                'city' => isset($BusinessAddress->mailing_city) ? $BusinessAddress->mailing_city : '',
                'state' => isset($BusinessAddress->mailing_state) ? $BusinessAddress->mailing_state : '',
                'country' => isset($BusinessAddress->country) ? $BusinessAddress->country : '',
            ];
            $type = config('services.stripe.type', 'test');
            $controller = new self;
            $res = [];
            if ($profiledata->stripe_customer_id != '') {
                $res = $controller->stripecustomerupdate($profiledata->stripe_customer_id, $custadd, $type);
            } else {
                $res = $controller->stripecreatecustomer($custadd, $type);
                if ($res['id']) {
                    $pdata = ['stripe_customer_id' => $res['id']];
                    CompanyProfile::where('id', 1)->update($pdata);
                }
            }
            StripeResponseLog::create(['response' => json_encode($data)]);
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
        }

    }

    public function updatepaymentmethod(Request $request): JsonResponse
    {
        $profiledata = CompanyProfile::where('id', 1)->first();
        if ($request['payment_method']) {
            $data['invoice_settings']['default_payment_method'] = $request['payment_method'];
            $type = config('services.stripe.type', 'test');
            $this->stripeupdatepaymentmethod($profiledata->stripe_customer_id, $data, $type);

            $rdata = $this->getpaymentlist();

            // User::where('id', $userId)->update(['device_token' => $request['device_token']]);
            return response()->json([
                'ApiName' => 'updatepaymentmethod',
                'status' => true,
                'data' => $rdata,
                'message' => 'Successfully updated!',
            ], 200);
        }

        return response()->json([
            'ApiName' => 'updatepaymentmethod',
            'status' => true,
            'message' => 'Not updated!',
        ], 400);
    }

    public function setup_intents(): JsonResponse
    {
        $profiledata = CompanyProfile::where('id', 1)->first();
        $type = config('services.stripe.type', 'test');
        // $profiledata->stripe_customer_id
        $shipping['customer'] = $profiledata->stripe_customer_id;
        $shipping['use_stripe_sdk'] = 'true';
        $shipping['automatic_payment_methods'] = ['enabled' => 'true'];
        $data = $this->stripesetupintents($shipping, $type);
        StripeResponseLog::create(['response' => json_encode($data)]);
        if (isset($data['id'])) {
            $rdata['id'] = $data['id'];
            $rdata['client_secret'] = $data['client_secret'];

            return response()->json([
                'ApiName' => 'setup_intents',
                'status' => true,
                'data' => $rdata,
                'message' => 'Successfully created!',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'setup_intents',
                'status' => false,
                'message' => 'try again..!',
            ], 400);
        }

    }

    public function deletepaymentmethod(Request $request): JsonResponse
    {
        $profiledata = CompanyProfile::where('id', 1)->first();
        if ($request['payment_method']) {
            $type = config('services.stripe.type', 'test');
            $profile = $this->stripecustomerprofile($profiledata->stripe_customer_id, $data = [], $type);
            if (isset($profile['invoice_settings']['default_payment_method'])) {
                $default = $profile['invoice_settings']['default_payment_method'];
                if ($default == $request['payment_method']) {
                    $pndata = ['stripe_autopayment' => 0];
                    CompanyProfile::where('id', 1)->update($pndata);
                }
            }
            $this->stripedeletepaymentmethod($request['payment_method'], $data = [], $type);
            $rdata = $this->getpaymentlist();

            return response()->json([
                'ApiName' => 'deletepaymentmethod',
                'status' => true,
                'data' => $rdata,
                'message' => 'Successfully deleted!',
            ], 200);
        }

        return response()->json([
            'ApiName' => 'deletepaymentmethod',
            'status' => true,
            'message' => 'Not deleted!',
        ], 400);
    }

    public function updateautopayment(Request $request): JsonResponse
    {
        $profiledata = CompanyProfile::where('id', 1)->first();
        $type = config('services.stripe.type', 'test');
        $data = [];
        $profile = $this->stripecustomerprofile($profiledata->stripe_customer_id, $data, $type);
        if ($profile && isset($profile['invoice_settings']['default_payment_method']) && $profile['invoice_settings']['default_payment_method'] != '') {
            $stripe_autopayment = $request->stripe_autopayment ?? 0;
            $pdata = ['stripe_autopayment' => $stripe_autopayment];
            CompanyProfile::where('id', 1)->update($pdata);

            return response()->json([
                'ApiName' => 'updateautopayment',
                'status' => true,
                'message' => 'Successfully updated!',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'updateautopayment',
                'status' => false,
                'message' => 'Default Payment Method Not Set',
            ], 400);
        }
    }

    public function AutoInvoicePay($invoice_no)
    {
        $profiledata = CompanyProfile::where('id', 1)->first();

        Log::info('profiledata '.$profiledata);
        $pdata = [];
        if ($profiledata) {
            $data = SubscriptionBillingHistory::where('invoice_no', $invoice_no)->sum('amount');
            $historyinfo = SubscriptionBillingHistory::with('subscription')->where('invoice_no', $invoice_no)->first();
            $type = config('services.stripe.type', 'test');
            if (! empty($data)) {
                $amounts = number_format($data, 2, '.', '');
                $amounttopay = ($amounts * 100);
                // $subscription_id = $data->subscription_id;

                // $stripe_customer_id = $profiledata->stripe_customer_id;
                $start_date = isset($historyinfo->subscription) ? $historyinfo->subscription->start_date : '';
                $end_date = isset($historyinfo->subscription) ? $historyinfo->subscription->end_date : '';

                $desc = $historyinfo->invoice_no.' | Sequifi | '.$profiledata->name.' | '.'$'.$amounts.' | '
                    .date('m/d/Y', strtotime($start_date)).' - '.date('m/d/Y', strtotime($end_date)).' | '.$historyinfo->plan_name;

                $pay['currency'] = 'usd';
                $pay['collection_method'] = $profiledata->stripe_autopayment ? 'charge_automatically' : 'send_invoice';
                if (! $profiledata->stripe_autopayment) {
                    $pay['days_until_due'] = 15; // for due date to 15 days
                }
                $pay['metadata']['server_name'] = config('app.domain_name') ? config('app.domain_name') : config('app.env');
                $pay['metadata']['server_url'] = config('app.base_url') ? \Config::get('app.base_url') : \Config::get('app.url');
                $pay['description'] = $desc;

                $pay['shipping_details']['name'] = isset($profiledata->name) ? $profiledata->name : '';
                $pay['shipping_details']['phone'] = isset($profiledata->phone_number) ? $profiledata->phone_number : '';
                $BusinessAddress = BusinessAddress::first();
                $pay['shipping_details']['address'] = [
                    'line1' => isset($BusinessAddress->mailing_address) ? $BusinessAddress->mailing_address : '',
                    'postal_code' => isset($BusinessAddress->mailing_zip) ? $BusinessAddress->mailing_zip : '',
                    'city' => isset($BusinessAddress->mailing_city) ? $BusinessAddress->mailing_city : '',
                    'state' => isset($BusinessAddress->mailing_state) ? $BusinessAddress->mailing_state : '',
                    'country' => isset($BusinessAddress->country) ? $BusinessAddress->country : '',
                ];
                if ($profiledata->stripe_customer_id === null) {
                    $shipping['shipping'] = $pay['shipping_details'];
                    $shipping['shipping']['phone'] = isset($profiledata->phone_number) ? $profiledata->phone_number : '';
                    $customerdata = $shipping['shipping'];
                    $customerdata['shipping'] = $shipping['shipping'];
                    $customerdata['email'] = isset($profiledata->company_email) ? $profiledata->company_email : '';
                    $customerdata['phone'] = isset($profiledata->phone_number) ? $profiledata->phone_number : '';
                    $customerdata['metadata']['server_name'] = config('app.domain_name') ? config('app.domain_name') : config('app.env');
                    $customerdata['metadata']['server_url'] = config('app.base_url') ? \Config::get('app.base_url') : \Config::get('app.url');
                    $stripecustomer = $this->stripecreatecustomer($customerdata, $type);
                    // print_r($stripecustomer);
                    if ($stripecustomer && isset($stripecustomer['id'])) {
                        $pay['customer'] = $stripecustomer['id'];
                        $pdata = ['stripe_customer_id' => $stripecustomer['id']];
                        CompanyProfile::where('id', 1)->update($pdata);
                    }
                } else {
                    $pay['customer'] = $profiledata->stripe_customer_id;
                }

                $response = $this->stripecreateinvoice($pay, $type);
                StripeResponseLog::create(['response' => json_encode($response)]);
                $payment_id = '';
                if ($response && isset($response['id'])) {
                    $pay['amount'] = $amounttopay;
                    $payment_id = $response['id'];
                    $pdata =
                        [
                            'client_secret' => $payment_id,
                        ];

                    $update = SubscriptionBillingHistory::where('invoice_no', $invoice_no)->update($pdata);
                    if ($payment_id) {
                        $pay['invoice'] = $payment_id;
                        unset($pay['collection_method']);
                        unset($pay['days_until_due']);
                        unset($pay['shipping_details']);
                        unset($pay['metadata']);
                        $item_list = $this->stripeaddinvoiceitems($pay, $type);
                        StripeResponseLog::create(['response' => json_encode($item_list)]);
                        if ($item_list['id']) {
                            $pay = [];
                            $pay['payment_id'] = $payment_id;
                            $finalize = $this->stripeaddinvoiceFinalize($pay, $type);
                            StripeResponseLog::create(['response' => json_encode($finalize)]);
                            if ($finalize && isset($finalize['status'])) {
                                if ($finalize['status'] == 'open' && $finalize['amount_due'] > 0 && $finalize['amount_paid'] < $finalize['amount_due']) {
                                    if ($profiledata->stripe_autopayment) {
                                        SubscriptionBillingHistory::where('client_secret', $payment_id)
                                            ->update(['paid_status' => 3, 'last_payment_message' => null]);
                                    }
                                    $pay_url = isset($finalize['hosted_invoice_url']) ? $finalize['hosted_invoice_url'] : '';
                                    // $pndata =['payment_url'=> $pay_url];
                                    // SubscriptionBillingHistory::where('invoice_no',$invoice_no)->update($pndata);
                                    if (! empty($finalize['payment_intent'])) {
                                        $pudata['id'] = $payment_id;
                                        $pudata['payment_intent'] = $finalize['payment_intent'];
                                        $pudata['desc'] = $desc;
                                        $controller = new self;
                                        $controller->updatepaymentintenttoinvoice($type, $pudata);
                                    }
                                    if (! $profiledata->stripe_autopayment) {
                                        // $manualysend = $this->stripeaddinvoicemanualyysend($pay, $type);
                                        // StripeResponseLog::create(['response' => json_encode($manualysend)]);
                                        $manualysend = $this->sendInvoiceToMail($profiledata, $invoice_no, $payment_id, $type);  // to send email with our link of payment instead of stripe
                                    }

                                    if ($profiledata->stripe_autopayment) {
                                        $paid = $this->stripeaddinvoicePay($pay, $type);
                                        if ($paid && isset($paid['id'],$paid['status']) && $paid['status'] == 'paid') {
                                            $rresponse = SubscriptionBillingHistory::where('client_secret', $payment_id)
                                                ->update(['paid_status' => 1, 'last_payment_message' => null]);
                                        } elseif ($paid['status'] == 'open') {
                                            $rresponse = SubscriptionBillingHistory::where('client_secret', $payment_id)
                                                ->update(['paid_status' => 3, 'last_payment_message' => null]);
                                        } else {
                                            $rresponse = SubscriptionBillingHistory::where('client_secret', $payment_id)
                                                ->update(['paid_status' => 0, 'last_payment_message' => null]);
                                        }
                                        StripeResponseLog::create(['response' => json_encode($paid)]);
                                    }
                                }
                            } else {
                                SubscriptionBillingHistory::where('client_secret', $payment_id)
                                    ->update(['paid_status' => 0, 'last_payment_message' => 'finalize payment problem']);
                            }
                        } else {
                            SubscriptionBillingHistory::where('client_secret', $payment_id)
                                ->update(['paid_status' => 0, 'last_payment_message' => 'invoice item create']);
                        }
                    }
                }

                $pdata['payment_id'] = $payment_id;
            }
        }

        return $pdata;
    }

    public function client_secret($shipping, $type)
    {
        if ($type == 'live') {
            //    $stripe_key = config('services.stripe.key_live');
            $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
        } else {
            //    $stripe_key = config('services.stripe.key_test');
            $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
        }
        // $data1 = json_encode($shipping);
        $url = 'https://api.stripe.com/v1/payment_intents';

        $headers = [
            'accept: application/json, text/plain, */*',

            'authorization: Bearer '.$stripe_key, // live
            // 'authorization: Bearer '.config('services.stripe.key_test'), //test
            'content-type: application/x-www-form-urlencoded',
        ];

        $curl_response = $this->curlRequestData($url, $shipping, $headers, 'POST');
        $resp = json_decode($curl_response, true);

        // return $resp;
        return $resp['client_secret'];
    }

    public function stripecreatecustomer($shipping, $type)
    {
        try {

            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            // $data1 = json_encode($shipping);
            $url = 'https://api.stripe.com/v1/customers';

            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            // print_r($shipping);die();
            $curl_response = $this->curlRequestData($url, $shipping, $headers, 'POST');

            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('redirect-stripe-webhook', " error \n");
            createLogFile('redirect-stripe-webhook', $errorMessage);
        }

    }

    protected function stripegetinvoice($customerid, $type)
    {
        try {
            // $resp = json_decode($res, true);
            $pdata = [];
            // return $resp;
            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }

            $url = 'https://api.stripe.com/v1/invoices?customer='.$customerid.'&status=open';
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];

            $curl_response = $this->curlRequestData($url, $pdata, $headers, 'GET');
            Log::info('resp '.$curl_response);
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripecreateinvoice($pdata, $type)
    {
        try {
            // $res  = '{"id":"in_1PXhj1CRUyxgHsgPpKhwcmqN","object":"invoice","account_country":"US","account_name":"Sequifi Inc.","account_tax_ids":null,"amount_due":0,"amount_paid":0,"amount_remaining":0,"amount_shipping":0,"application":null,"application_fee_amount":null,"attempt_count":0,"attempted":false,"auto_advance":false,"automatic_tax":{"enabled":false,"liability":null,"status":null},"billing_reason":"manual","charge":null,"collection_method":"charge_automatically","created":1719830687,"currency":"usd","custom_fields":null,"customer":"cus_QLst6kD8PUKRn6","customer_address":{"city":"Provo","country":"US","line1":"55 N University Ave ste 220 222, Downtown, Provo, Utah, 84601","line2":null,"postal_code":"84601","state":"Utah"},"customer_email":"team@sequifi.org","customer_name":"Sequifi","customer_phone":"3852546071","customer_shipping":{"address":{"city":"Provo","country":"US","line1":"55 N University Ave ste 220 222, Downtown, Provo, Utah, 84601","line2":null,"postal_code":"84601","state":"Utah"},"name":"Sequifi","phone":"3852546071"},"customer_tax_exempt":"none","customer_tax_ids":[],"default_payment_method":null,"default_source":null,"default_tax_rates":[],"description":"S-1715976498 - Sequifi | $ 536.25 | Basic(Monthly)","discount":null,"discounts":[],"due_date":null,"effective_at":null,"ending_balance":null,"footer":null,"from_invoice":null,"hosted_invoice_url":null,"invoice_pdf":null,"issuer":{"type":"self"},"last_finalization_error":null,"latest_revision":null,"lines":{"object":"list","data":[],"has_more":false,"total_count":0,"url":"\/v1\/invoices\/in_1PXhj1CRUyxgHsgPpKhwcmqN\/lines"},"livemode":false,"metadata":[],"next_payment_attempt":null,"number":null,"on_behalf_of":null,"paid":false,"paid_out_of_band":false,"payment_intent":null,"payment_settings":{"default_mandate":null,"payment_method_options":null,"payment_method_types":null},"period_end":1719830687,"period_start":1719830687,"post_payment_credit_notes_amount":0,"pre_payment_credit_notes_amount":0,"quote":null,"receipt_number":null,"rendering":{"amount_tax_display":null,"pdf":{"page_size":"auto"}},"rendering_options":null,"shipping_cost":null,"shipping_details":{"address":{"city":"Provo","country":"US","line1":"55 N University Ave ste 220 222, Downtown, Provo, Utah, 84601","line2":null,"postal_code":"84601","state":"Utah"},"name":"Sequifi","phone":"3852546071"},"starting_balance":0,"statement_descriptor":null,"status":"draft","status_transitions":{"finalized_at":null,"marked_uncollectible_at":null,"paid_at":null,"voided_at":null},"subscription":null,"subscription_details":{"metadata":null},"subtotal":0,"subtotal_excluding_tax":0,"tax":null,"test_clock":null,"total":0,"total_discount_amounts":[],"total_excluding_tax":0,"total_tax_amounts":[],"transfer_data":null,"webhooks_delivered_at":1719830687}';
            // $resp = json_decode($res, true);

            // return $resp;
            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }

            $url = 'https://api.stripe.com/v1/invoices';
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];

            $curl_response = $this->curlRequestData($url, $pdata, $headers, 'POST');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripeaddinvoiceitems($data, $type)
    {
        try {

            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $url = 'https://api.stripe.com/v1/invoiceitems';
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripeaddinvoiceVoid($data, $type)
    {
        try {

            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $invoice_id = isset($data['payment_id']) ? $data['payment_id'] : '';
            $data = [];
            $url = 'https://api.stripe.com/v1/invoices/'.$invoice_id.'/void';
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripeaddinvoiceFinalize($data, $type)
    {
        try {

            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $invoice_id = isset($data['payment_id']) ? $data['payment_id'] : '';
            $data = [];
            $url = 'https://api.stripe.com/v1/invoices/'.$invoice_id.'/finalize';
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripeaddinvoicemanualyysend($data, $type)
    {
        try {

            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $invoice_id = isset($data['payment_id']) ? $data['payment_id'] : '';
            $data = [];
            $url = 'https://api.stripe.com/v1/invoices/'.$invoice_id.'/send';
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripeaddinvoicePay($data, $type)
    {
        try {

            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $invoice_id = isset($data['payment_id']) ? $data['payment_id'] : '';
            $url = 'https://api.stripe.com/v1/invoices/'.$invoice_id.'/pay';
            $data = [];
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripeupdatepaymentmethod($customer_id, $data, $type)
    {
        try {
            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $url = 'https://api.stripe.com/v1/customers/'.$customer_id;
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripesetupintents($data, $type)
    {
        try {
            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $url = 'https://api.stripe.com/v1/setup_intents';
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripedeletepaymentmethod($payment_id, $data, $type)
    {
        try {
            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $url = 'https://api.stripe.com/v1/payment_methods/'.$payment_id.'/detach';
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripecustomerprofile($customer_id, $data, $type)
    {
        try {
            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $url = 'https://api.stripe.com/v1/customers/'.$customer_id;
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'GET');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripecustomerupdate($customer_id, $data, $type)
    {
        try {
            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $url = 'https://api.stripe.com/v1/customers/'.$customer_id;
            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripegetpaymentlist($customer_id, $data, $type)
    {
        try {

            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }
            $url = 'https://api.stripe.com/v1/customers/'.$customer_id.'/payment_methods';

            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'GET');
            $resp = json_decode($curl_response, true);

            return $resp;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    public function stripeCallbackUrl(Request $request): JsonResponse
    {

        if (! empty($request->billing_id) && ($request->billing_id != 0) && ! empty($request->payment_intent)) {
            $type = config('services.stripe.type', 'test'); // ($request->type == 'live') ? 'live':'test';
            if ($type == 'live') {
                //    $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                //    $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }

            $payment_intent = $request->payment_intent;
            $url = 'https://api.stripe.com//v1/payment_intents/'.$payment_intent;
            $headers = [
                'authorization: Bearer '.$stripe_key,
                'content-type: application/x-www-form-urlencoded',
            ];

            $curl_response = $this->curlRequestData($url, [], $headers, 'GET');
            $resp = json_decode($curl_response, true);

            $data = SubscriptionBillingHistory::where('invoice_no', $request->billing_id)->first();
            if (! empty($data) && ! empty($resp)) {

                $client_secret = $resp['client_secret'];
                $status = $resp['status'];
                if ($data->client_secret == $client_secret && $status == 'succeeded') {
                    $data->paid_status = 1;
                    // $data->stripe_response = $resp;
                    // $data->save();
                    SubscriptionBillingHistory::where('invoice_no', $request->billing_id)->update(['paid_status' => 1]);
                }
            }

            return response()->json([
                'ApiName' => 'stripe_callback_url',
                'status' => true,
                'message' => 'Successfully.',
            ], 200);
        }
    }

    public function curlRequestData($url, $shipping, $headers, $method = 'POST')
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => http_build_query($shipping),
            CURLOPT_HTTPHEADER => $headers,

        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    public function stripeWebhookUrl(Request $request)
    {
        // $alldata = $request->all();

        $rawData = $request->getContent();
        StripeResponseLog::create(['response' => $rawData]);
        // $result = StripeResponseLog::orderBy('id','DESC')->first();
        // $data = json_decode($result->response, true);

        $data = json_decode($rawData, true);

        if (isset($data['type'])) {

            if (isset($data['data']) && ! empty($data['data']['object'])) {
                $paymentId = $data['data']['object']['id'];
                $clientSecret = $data['data']['object']['client_secret'];
                $status = $data['data']['object']['status'];

                if (isset($data['data']['object']['last_payment_error']) && ! empty($data['data']['object']['last_payment_error'])) {
                    $message = $data['data']['object']['last_payment_error']['message'];
                } else {
                    $message = null;
                }

                if ($status == 'succeeded') {
                    // $subscriptiondata = SubscriptionBillingHistory::where('client_secret',$clientSecret)->first();
                    // if(!empty($subscriptiondata))
                    // {
                    // }

                    SubscriptionBillingHistory::where('client_secret', $clientSecret)->update(['paid_status' => 1, 'last_payment_message' => $message]);

                } elseif ($status == 'requires_action') {
                    SubscriptionBillingHistory::where('client_secret', $clientSecret)->update(['paid_status' => 2, 'last_payment_message' => $message]);

                } elseif ($status == 'processing') {
                    SubscriptionBillingHistory::where('client_secret', $clientSecret)->update(['paid_status' => 3, 'last_payment_message' => $message]);

                } elseif ($status == 'payment_failed') {
                    SubscriptionBillingHistory::where('client_secret', $clientSecret)->update(['paid_status' => 0, 'last_payment_message' => $message]);
                } elseif ($status == 'requires_payment_method') {
                    // return $status;
                    SubscriptionBillingHistory::where('client_secret', $clientSecret)->update(['paid_status' => 0, 'last_payment_message' => $message]);
                } elseif ($status == 'paid') {
                    // return $status;//success invoice
                    SubscriptionBillingHistory::where('client_secret', $paymentId)->update(['paid_status' => 1, 'last_payment_message' => $message]);
                } elseif ($status == 'open') {
                    // return $status;//success invoice
                    SubscriptionBillingHistory::where('client_secret', $paymentId)->update(['paid_status' => 3, 'last_payment_message' => $message]);
                }

            }
            // return $clientSecret;

        }

    }

    public function redirectWebhookUrl(Request $request)
    {
        date_default_timezone_set('Asia/Kolkata');
        try {
            createLogFile('redirect-stripe-webhook', date('d-m-Y H:i:s')." call redirect function \n");
            $rawData = $request->getContent();
            $data = json_decode($rawData, true);
            $webhookDomainName = $data['data']['object']['metadata']['server_name'] ?? null;
            $serverUrl = $data['data']['object']['metadata']['server_url'] ?? null;
            $currentDomainName = config('app.domain_name') ? config('app.domain_name') : config('app.name');
            if ($serverUrl) {
                createLogFile('redirect-stripe-webhook', 'start');
                if ($currentDomainName === $webhookDomainName) {
                    createLogFile('redirect-stripe-webhook', 'same domain');
                    // Call stripeWebhookUrl locally
                    $this->callSameDomaiStripeWebhookUrl($data);
                } else {
                    createLogFile('redirect-stripe-webhook', 'different domain');
                    if (substr($serverUrl, -1) === '/') {
                        createLogFile('redirect-stripe-webhook', 'if');
                        $serverUrl .= 'api/stripe_redirect_webhook_url';
                    } else {
                        createLogFile('redirect-stripe-webhook', 'else');
                        $serverUrl .= '/api/stripe_redirect_webhook_url';
                    }
                    createLogFile('redirect-stripe-webhook', $serverUrl);
                    $response = Http::post($serverUrl, [
                        'data' => $request->all(),
                    ]);
                    createLogFile('redirect-stripe-webhook', '====================');
                    createLogFile('redirect-stripe-webhook', $response->body());
                    createLogFile('redirect-stripe-webhook', '====================');
                }
                createLogFile('redirect-stripe-webhook', date('d-m-Y H:i:s')." end \n");
            }
            createLogFile('redirect-stripe-webhook', date('d-m-Y H:i:s')." end not redirect function \n");
            // code...
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('redirect-stripe-webhook', " error \n");
            createLogFile('redirect-stripe-webhook', $errorMessage);
        }
    }

    public function stripeWebhookUrlMultipleServer(Request $requestData)
    {
        date_default_timezone_set('Asia/Kolkata');
        createLogFile('stripe-webhook-multiple-url', date('d-m-Y H:i:s')." start \n");
        StripeResponseLog::create(['response' => json_encode($requestData->all(), true)]);
        $data = $requestData->all();
        $data = $data['data'];
        if (isset($data['type'])) {
            createLogFile('stripe-webhook-multiple-url', 'if');
            createLogFile('stripe-webhook-multiple-url', $data);
            try {
                if (isset($data['data']) && ! empty($data['data']['object'])) {
                    createLogFile('stripe-webhook-multiple-url', 'before payment trigger name => '.$data['data']['object']['object']);
                    if ($data['data']['object']['object'] !== 'charge') {
                        $paymentId = $data['data']['object']['id'];
                        $clientSecret = isset($data['data']['object']['client_secret']) ? $data['data']['object']['client_secret'] : '';
                        $status = $data['data']['object']['status'];
                        if (isset($data['data']['object']['last_payment_error']) && ! empty($data['data']['object']['last_payment_error'])) {
                            $message = $data['data']['object']['last_payment_error']['message'];
                        } else {
                            $message = null;
                        }
                        $response = [];
                        if ($status == 'succeeded') {
                            $response = SubscriptionBillingHistory::where('client_secret', $clientSecret)
                                ->update(['paid_status' => 1, 'last_payment_message' => $message]);
                        } elseif ($status == 'requires_action') {
                            $response = SubscriptionBillingHistory::where('client_secret', $clientSecret)
                                ->update(['paid_status' => 2, 'last_payment_message' => $message]);
                        } elseif ($status == 'processing') {
                            $response = SubscriptionBillingHistory::where('client_secret', $clientSecret)
                                ->update(['paid_status' => 3, 'last_payment_message' => $message]);
                        } elseif ($status == 'payment_failed' || $status == 'requires_payment_method') {
                            $response = SubscriptionBillingHistory::where('client_secret', $clientSecret)
                                ->update(['paid_status' => 0, 'last_payment_message' => $message]);
                        } elseif ($status == 'paid') {
                            // return $status;//success invoice
                            if ($data['type'] == 'invoice.payment_succeeded') {
                                SubscriptionBillingHistory::where('client_secret', $paymentId)->update(['paid_status' => 1, 'last_payment_message' => $message]);
                            }
                        }

                        createLogFile('stripe-webhook-multiple-url', [
                            'data' => $response,
                            'message' => $message,
                        ]);

                        return response()->json([
                            'data' => $response,
                            'message' => $message ?? 'Success',
                        ]);
                    }
                } else {
                    createLogFile('stripe-webhook-multiple-url', 'else data of data is not found');
                }
                createLogFile('stripe-webhook-multiple-url', date('d-m-Y H:i:s')." end \n");
            } catch (\Throwable $th) {
                $errorMessage = $th->getMessage().' => '.$th->getLine();
                createLogFile('stripe-webhook-multiple-url', " error \n");
                createLogFile('stripe-webhook-multiple-url', $errorMessage);
            }
        }
    }

    public function callSameDomaiStripeWebhookUrl($data)
    {

        createLogFile('same-domain-webhook-log', 'start');
        if (isset($data['type'])) {
            createLogFile('same-domain-webhook-log', 'if');
            try {
                if (isset($data['data']) && ! empty($data['data']['object'])) {
                    createLogFile('same-domain-webhook-log', 'before payment trigger name => '.$data['data']['object']['object']);
                    if ($data['data']['object']['object'] !== 'charge') {
                        $paymentId = isset($data['data']['object']['id']) ? $data['data']['object']['id'] : '';
                        $clientSecret = isset($data['data']['object']['client_secret']) ? $data['data']['object']['client_secret'] : '';
                        $status = $data['data']['object']['status'];
                        if (isset($data['data']['object']['last_payment_error']) && ! empty($data['data']['object']['last_payment_error'])) {
                            $message = $data['data']['object']['last_payment_error']['message'];
                        } else {
                            $message = null;
                        }
                        $response = [];
                        if ($status == 'succeeded') {
                            $response = SubscriptionBillingHistory::where('client_secret', $clientSecret)
                                ->update(['paid_status' => 1, 'last_payment_message' => $message]);
                        } elseif ($status == 'requires_action') {
                            $response = SubscriptionBillingHistory::where('client_secret', $clientSecret)
                                ->update(['paid_status' => 2, 'last_payment_message' => $message]);
                        } elseif ($status == 'processing') {
                            $response = SubscriptionBillingHistory::where('client_secret', $clientSecret)
                                ->update(['paid_status' => 3, 'last_payment_message' => $message]);
                        } elseif ($status == 'payment_failed' || $status == 'requires_payment_method') {
                            $response = SubscriptionBillingHistory::where('client_secret', $clientSecret)
                                ->update(['paid_status' => 0, 'last_payment_message' => $message]);
                        } elseif ($status == 'paid') {
                            // return $status;//success invoice
                            if ($data['type'] == 'invoice.payment_succeeded') {
                                SubscriptionBillingHistory::where('client_secret', $paymentId)->update(['paid_status' => 1, 'last_payment_message' => $message]);
                            }
                        }

                        createLogFile('same-domain-webhook-log', [
                            'data' => $response,
                            'message' => $message,
                        ]);

                        return response()->json([
                            'data' => $response,
                            'message' => $message ?? 'Success',
                        ]);
                    }
                    createLogFile('same-domain-webhook-log', date('d-m-Y H:i:s')." end \n");
                } else {
                    createLogFile('same-domain-webhook-log', 'else data of data is not found');
                }
            } catch (\Throwable $th) {
                $errorMessage = $th->getMessage().' => '.$th->getLine();
                createLogFile('same-domain-webhook-log', " error \n");
                createLogFile('same-domain-webhook-log', $errorMessage);
            }
        } else {
            createLogFile('same-domain-webhook-log', 'nhi aaya');
        }
    }

    /**
     * @method SClearanceData
     * method to get S Clearance data
     */
    public function SClearanceData($subscription, $type = '')
    {
        $sClearancePlanId = $subscription->plans->sclearance_plan_id;
        $start_date = $subscription->start_date;
        $end_date = $subscription->end_date;
        if ($type == 'history') {
            $start_date = $subscription->subscription->start_date;
            $end_date = $subscription->subscription->end_date;
        }

        $reportData = SClearanceTurnScreeningRequestList::select('package_id', 'state', 'turn_id')->whereBetween('date_sent', [$start_date, $end_date])->whereNotNull('package_id')->get()->toArray();

        if (! empty($reportData)) {
            $totalAmount = 0;
            $reportCount = 0;
            foreach ($reportData as $report) {
                $amount = 0;
                $planData = SClearancePlan::where('package_id', '=', $report['package_id'])->first();

                if (! empty($planData) && isset($planData->price)) {
                    if ($planData->plan_name == 'MVR Only') {
                        $stateCost = StateMVRCost::select('cost')->where('state_code', $report['state'])->first();
                        $planData->price = $planData->price + (isset($stateCost->cost) ? $stateCost->cost : 0);
                    }
                    $amount = $planData->price;
                }
                $totalAmount += $amount;
                $reportCount++;
            }

            $reportCount = ($totalAmount == 0) ? 0 : $reportCount;
            $sales_tax_per = isset($subscription->subscription->sales_tax_per) && $subscription->subscription->sales_tax_per > 0 ? $subscription->subscription->sales_tax_per : 7.25;
            $sales_tax_amount = (($totalAmount * $sales_tax_per) / 100);
            $grandTotal = ($totalAmount + $sales_tax_amount);

            $response = [
                'price' => 0,
                'report_count' => $reportCount,
                'total' => round($grandTotal, 2),
            ];
        } else {
            $response = [
                'price' => 0,
                'report_count' => 0,
                'total' => 0,
            ];
        }

        return $response;
    }

    /**
     * @method userWiseBillingData
     * method to get user wise billing data
     */
    public static function userWiseBillingData($subscription)
    {
        $start_date = $subscription->start_date;
        $end_date = $subscription->end_date;
        if (isset($subscription->active_user_billing) && $subscription->active_user_billing == 1) {
            $payroll_histry_id_query = User::with('office:id,office_name', 'positionDetail:id,position_name')->select('id', 'first_name', 'last_name', 'employee_id', 'office_id', 'sub_position_id', 'position_id')->where('dismiss', 0)
            ->whereNotIn('email', config('constant.exclude_users_from_active_billing_by_email'))
            ->where('created_at', '<', $end_date);
        } else {
            $saleUsers = [];
            $loggedUsers = [];
            $paidUsers = [];
            if (isset($subscription->sale_approval_active_user_billing) && $subscription->sale_approval_active_user_billing == 1) {
                // get users of sale having aprroval date of current month
                $saleUsers = SalesMaster::selectRaw('GROUP_CONCAT(DISTINCT CONCAT_WS(",", closer1_id, setter1_id, closer2_id, setter2_id)) AS user_id')
                    ->whereBetween('customer_signoff', [$start_date, $end_date])
                    ->first()->toArray();
            }

            if (isset($subscription->logged_in_active_user_billing) && $subscription->logged_in_active_user_billing == 1) {
                // get users logged in this month
                $loggedUsers = User::selectRaw('GROUP_CONCAT(id) AS user_id')
                    ->whereBetween('last_login_at', [$start_date, $end_date])
                    ->first()->toArray();
            }

            if (isset($subscription->paid_active_user_billing) && $subscription->paid_active_user_billing == 1) {
                // get users paid this month
                $paidUsers = PayrollHistory::selectRaw('GROUP_CONCAT(DISTINCT user_id) AS user_id')
                    ->where('status', 3)
                    ->whereBetween('created_at', [$start_date, $end_date])
                    ->first()->toArray();
            }

            // get users paid this month
            $OTPaidUsers = OneTimePayments::selectRaw('GROUP_CONCAT(DISTINCT user_id) AS user_id')
                ->where('payment_status', 3)
                ->whereBetween('pay_date', [$start_date, $end_date])
                ->first()->toArray();

            $allUsers = [$paidUsers, $loggedUsers, $saleUsers, $OTPaidUsers];

            // Extract all user IDs and merge into a single array
            $mergedUserIds = [];
            foreach ($allUsers as $array) {
                $mergedUserIds = array_merge($mergedUserIds, explode(',', @$array['user_id']));
            }

            // Remove duplicate values and reindex array
            $mergedUserIds = array_unique($mergedUserIds);
            $mergedUserIds = array_values($mergedUserIds);

            // Convert back to a comma-separated string if needed
            $finalUserIds = implode(',', $mergedUserIds);

            $payroll_histry_id_query = User::with('office:id,office_name', 'positionDetail:id,position_name')->select('id', 'first_name', 'last_name', 'employee_id', 'office_id', 'sub_position_id', 'position_id')->whereIn('id', $mergedUserIds)
            ->whereNotIn('email', config('constant.exclude_users_from_active_billing_by_email'))
            ->where('dismiss', 0);
        }

        if (request()->has('search') && ! empty(request()->input('search'))) {

            $payroll_histry_id_query->where(DB::raw("concat(first_name, ' ', last_name)"), 'like', '%'.request()->input('search').'%');

        }
        $final_payroll_hiistry_id = $payroll_histry_id_query->get()->toArray();

        $response['user_id'] = count($final_payroll_hiistry_id);
        $response['user_id_data'] = $final_payroll_hiistry_id;

        // $one_time_date_query = OneTimePayments::with(['usersdata' => function ($query) {
        //     $query->select('id', 'first_name', 'last_name', 'email');
        // }])->select('id','user_id','everee_external_id','everee_payment_req_id as everee_payment_requestId','everee_paymentId','amount as net_pay','pay_date as pay_period_from','pay_date as pay_period_to','everee_webhook_response as everee_webhook_json','created_at')->whereDate('created_at','<=',$subscription_end_date)->whereDate('created_at','>=',$subscription_start_date);

        // if(request()->has('search') && !empty(request()->input('search'))){
        //     $payroll_histry_id_query->whereHas('usersdata',function($query) {
        //         return $query->where(DB::raw("concat(first_name, ' ', last_name)"), 'like', '%'.request()->input('search').'%');
        //     });
        // }

        // $final_one_time_date_id = [];
        // $final_one_time_date_id = $one_time_date_query->get()->toArray();

        // $response['one_time_payment_date'] = count($final_one_time_date_id);
        // $response['one_time_payment_date_data'] = $final_one_time_date_id;

        return $response;
    }

    // get Pids data
    public function getUsersData(Request $request): JsonResponse
    {
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $unique_pid_rack_price = $unique_pid_discount_price = $m2_rack_price = $m2_discount_price = $pid_count = 0;
        $total_price = $sales_tax_amount = $sales_tax_per = $grand_total = $pid_kw_sum = 0;
        $pricebilled = [];
        $getdata = [];
        $status_code = 400;
        $status = false;
        $message = 'subscription not found!';
        if (! empty($request->subscription_id) && ($request->subscription_id != 0)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $request->subscription_id)->first();
        }
        if (! empty($request->billing_history_id) && ($request->billing_history_id != 0)) {
            $subscription_billing_history = SubscriptionBillingHistory::where('id', $request->billing_history_id)->first();
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $subscription_billing_history->subscription_id)->first();
        }
        // $subscription = Subscriptions::with('plans','billingType')->where('id',13)->first();
        if (! empty($subscription) && $subscription != null) {
            $status_code = 200;
            $status = true;
            $message = 'Data get!';

            $subscription_end_date = $subscription->end_date;
            $subscription_start_date = $subscription->start_date;

            $permars_array = [];
            $permars_array['subscription_end_date'] = $subscription_end_date;
            $permars_array['subscription_start_date'] = $subscription_start_date;

            // $unique_pids_m2_date_datas = SalesInvoiceDetail::unique_pids_m2_date_datas($permars_array);
            $user_billing = $this->userWiseBillingData($subscription);

            $users_billing_data = $user_billing['user_id_data'];
            $users_count = count($users_billing_data);

            // calculation
            $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
            $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
            $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
            $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

            $payroll_total_amount = $users_count * $unique_pid_discount_price;
            $total_price = $payroll_total_amount;

            $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
            $sales_tax_amount = (($total_price * $sales_tax_per) / 100);
            $grand_total = ($total_price + $sales_tax_amount);
            $payment_total = 0;
            foreach ($users_billing_data as $data) {
                $billed_price = $unique_pid_discount_price;
                // $payment_total += $data['net_pay'];
                $getdata[] = [
                    'id' => $data['employee_id'],
                    'user_name' => $data['first_name'].' '.$data['last_name'],
                    'position' => @$data['position_detail']['position_name'],
                    'office' => @$data['office']['office_name'],
                    'billed_price' => round($billed_price, 2),
                ];
            }

        }

        $getdata = paginate($getdata, $perpage);

        return response()->json([
            'ApiName' => 'get_pidsdata',
            'status' => $status,
            'message' => $message,
            'pid_total' => $users_count,
            'kw_total' => round($pid_kw_sum, 2),
            'total_price' => round($total_price, 2),
            'sales_tax_amount' => round($sales_tax_amount, 2),
            'sales_tax_per' => $sales_tax_per,
            'total_price_without_tex' => round($total_price, 2),
            'total_price_with_tex' => round($grand_total, 2),
            'unique_pid_rack_price' => $unique_pid_rack_price,
            'unique_pid_discount_price' => $unique_pid_discount_price,
            'm2_rack_price' => $m2_rack_price,
            'm2_discount_price' => $m2_discount_price,
            'data' => $getdata,
        ], $status_code);
    }

    /**
     * @method getBillingStatus
     * method to get get status of last month billing data
     */
    public static function getBillingStatus(): JsonResponse
    {

        $history = SubscriptionBillingHistory::with('subscription')->groupBy('invoice_no')->where('paid_status', 0)->orderBy('invoice_no', 'DESC')->get()->toArray();
        if (! empty($history) && count($history) > 1) {
            $result = [
                'paid_status' => false,
                'last_due_date' => $history[0]['subscription']['end_date'],
            ];
        } else {
            $result = [
                'paid_status' => true,
                'last_due_date' => '',
            ];
        }

        return response()->json($result);
    }

    /**
     * @method SequiAi Data
     * method to get SequiAi Data
     */
    public function SequiAiData($subscription)
    {
        $start_date = $subscription->start_date;
        $end_date = $subscription->end_date;

        $subscription_billing_history_id = 0;

        $seconLastSubscribtion = Subscriptions::where('plan_id', 4)->whereNotIn('id', [$subscription->id])->limit(1)->orderby('subscriptions.id', 'DESC')->first();

        $subBillingHistory = SubscriptionBillingHistory::where('subscription_id', $seconLastSubscribtion->id ?? 0)->where('plan_id', 4)->first();
        if ($subBillingHistory != null) {
            $subscription_billing_history_id = $subBillingHistory->id;
        }

        $sequiAiPlanCrmSetting = CrmSetting::where('crm_id', 6)->first();
        $historyIds = SequiaiRequestHistory::where('subscription_billing_history_id', $subscription_billing_history_id)->pluck('id')->toArray();
        // dd($historyIds);
        $response = [
            'price' => 0,
            'report_count' => 0,
            'total' => 0,
        ];

        if ($sequiAiPlanCrmSetting != null) {
            $sequiAiPlanValue = isset($sequiAiPlanCrmSetting->value) ? json_decode($sequiAiPlanCrmSetting->value, true) : [];
            $sequiai_plan_id = isset($sequiAiPlanValue['sequiai_plan_id']) ?? 0;

            $sequiaiPlan = SequiaiPlan::find($sequiai_plan_id);
            if ($sequiaiPlan != null) {
                $historyIds = SequiaiRequestHistory::where('subscription_billing_history_id', $subscription_billing_history_id)->pluck('id')->toArray();
                // if(count($historyIds) > 0 || $sequiAiPlanCrmSetting->status==1){
                // if($sequiAiPlanCrmSetting->status==1){
                $min_request = (int) $sequiaiPlan->min_request;
                $min_request_price = (float) $sequiaiPlan->price;

                $records = count($historyIds) / $min_request;
                $records_roundup = (int) $records;
                if (is_float($records)) {
                    $records_roundup++;
                }
                if ($records_roundup != 0) {
                    $total = $records_roundup * $min_request_price;
                } else {
                    $total = $min_request_price;
                }

                // $getRrecords = SequiaiRequestHistory::where(['status'=> 0])->pluck('id')->toArray();

                $response = [
                    'price' => $min_request_price,
                    'report_count' => $records_roundup,
                    'total' => $total,
                ];
                // }
            }
        }

        // dd($response, $subscription);
        return $response;
    }

    // get_Sales_Invoice_adjusted_kw_data for invoice details
    public function get_Sales_Invoice_adjusted_kw_data(Request $request): JsonResponse
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $billing_history_id = $request->billing_history_id;
        $subscription_id = $request->subscription_id;

        $history = SubscriptionBillingHistory::where('id', '=', $billing_history_id)->first();

        $status_code = 400;
        $status = false;
        $message = 'No data found!! Invaild billing history id';

        $data = [];
        if (! empty($history && $history != null)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $history->subscription_id)->first();

            $status_code = 200;
            $status = true;
            $message = 'Data get sucssesfully';
            if (empty($subscription || $subscription == null)) {
                $message = 'Plan subscription deleted!';
            }

            $plan = isset($history->plan_name) ? $history->plan_name : null;
            $unique_pid_rack_price = isset($history->unique_pid_rack_price) ? $history->unique_pid_rack_price : null;
            $unique_pid_discount_price = isset($history->unique_pid_discount_price) ? $history->unique_pid_discount_price : null;
            $m2_rack_price = isset($history->m2_rack_price) ? $history->m2_rack_price : null;
            $m2_discount_price = isset($history->m2_discount_price) ? $history->m2_discount_price : null;

            $datalist = SalesInvoiceDetail::where('billing_history_id', $billing_history_id)->where('invoice_for', '=', 'm2_date')->where('is_kw_adjusted_invoice', '=', '1');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $datalist->where(function ($query) use ($request) {
                    $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%');
                });
            }
            $SalesInvoiceDetail_data = $datalist->get();

            $kw_count = $datalist->count('kw');
            $kw_sum = $datalist->sum('invoice_generated_on_kw');

            $total_amount = ($kw_sum * 1000 * $m2_discount_price);
            $total_price = ($total_amount);

            $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
            $sales_tax_amount = (($total_price * $sales_tax_per) / 100);
            $grand_total = ($total_price + $sales_tax_amount);

            foreach ($SalesInvoiceDetail_data as $row) {
                $billed_price = ($m2_discount_price * $row->invoice_generated_on_kw) * 1000;
                if ($row->invoice_generated_on_kw < 0) {
                    $old_kw = ($row->kw - $row->invoice_generated_on_kw);
                } else {
                    $old_kw = ($row->kw + $row->invoice_generated_on_kw);
                }
                $data[] = [
                    'id' => $row->id,
                    'pid' => $row->pid,
                    'customer_name' => $row->customer_name,
                    'customer_state' => $row->customer_state,
                    'data_from' => $row->data_from,
                    'adjusted_kw' => $row->kw,
                    'kw_diff' => $row->invoice_generated_on_kw,
                    'old_kw' => $old_kw,
                    'm2_date' => $row->m2_date,
                    'approval_date' => $row->customer_signoff,
                    'price' => $m2_discount_price,
                    'billed_price' => round($billed_price, 2),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }
            $data = paginate($data, $perpage);

            return response()->json([
                'ApiName' => 'get_Sales_Invoice_adjusted_kw_data',
                'status' => $status,
                'message' => $message,
                'pid_total_count' => $kw_count,
                'adjusted_kw_total' => $kw_count,
                'kw_total' => round($kw_sum, 2),
                'total_price' => round($total_price, 2),
                'sales_tax_amount' => round($sales_tax_amount, 2),
                'total_price_with_tex' => round($grand_total, 2),
                'sales_tax_per' => $sales_tax_per,
                'unique_pid_rack_price' => $unique_pid_rack_price,
                'unique_pid_discount_price' => $unique_pid_discount_price,
                'm2_rack_price' => $m2_rack_price,
                'm2_discount_price' => $m2_discount_price,
                'data' => $data,
            ], $status_code);
        }

        return response()->json([
            'ApiName' => 'get_Sales_Invoice_adjusted_kw_data',
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status_code);
    }

    // get adjusted kw data
    public function getadjustedkwdata(Request $request): JsonResponse
    {
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $total_adjusted_kw = '';
        $adjusted_kw_sum = '';
        $unique_pid_rack_price = $unique_pid_discount_price = $m2_rack_price = $m2_discount_price = $pid_count = 0;
        $pricebilled = [];
        $getdata = [];
        $status_code = 400;
        $status = false;
        $message = 'subscription not found!';
        $total_price = $sales_tax_amount = $sales_tax_per = $grand_total = $pid_kw_sum = 0;
        if (! empty($request->subscription_id) && ($request->subscription_id != 0)) {
            $subscription = Subscriptions::with('plans', 'billingType')->where('id', $request->subscription_id)->first();
            if (! empty($subscription)) {
                $status_code = 200;
                $status = true;
                $message = 'Data get!';

                $subscription_end_date = $subscription->end_date;
                $subscription_start_date = $subscription->start_date;

                $permars_array = [];
                $permars_array['subscription_end_date'] = $subscription_end_date;
                $permars_array['subscription_start_date'] = $subscription_start_date;

                $unique_pids_m2_date_datas = SalesInvoiceDetail::unique_pids_m2_date_datas($permars_array);

                if (isset($unique_pids_m2_date_datas['kw_adjusted_data']) && ! empty($unique_pids_m2_date_datas['kw_adjusted_data'])) {
                    $kw_adjusted_data = $unique_pids_m2_date_datas['kw_adjusted_data'];

                    $total_adjusted_kw = count($kw_adjusted_data);
                    $adjusted_kw_sum = round(array_sum(array_column($kw_adjusted_data, 'kw')), 2);

                    // calculation
                    $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
                    $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
                    $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
                    $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;

                    $adjusted_kw_total_amount = (float) ($adjusted_kw_sum * 1000 * $m2_discount_price);
                    $total_price = (float) ($adjusted_kw_total_amount);

                    $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
                    $sales_tax_amount = (float) (($total_price * $sales_tax_per) / 100);
                    $grand_total = (float) ($total_price + $sales_tax_amount);

                    foreach ($kw_adjusted_data as $data) {
                        $billed_price = ($m2_discount_price * $data['kw']) * 1000;
                        $getdata[] = [
                            'id' => $data['id'],
                            'pid' => $data['pid'],
                            'customer_name' => $data['customer_name'],
                            'customer_state' => $data['customer_state'],
                            'data_from' => $data['data_from'],
                            'adjusted_kw' => $data['updated_kw'],
                            'kw_diff' => round($data['kw_diff'], 2),
                            'old_kw' => $data['kw'],
                            'm2_date' => $data['m2_date'],
                            'approval_date' => $data['customer_signoff'],
                            'price' => $m2_discount_price,
                            'billed_price' => round($billed_price, 2),
                        ];
                    }
                }

            }
        }

        $getdata = paginate($getdata, $perpage);

        return response()->json([
            'ApiName' => 'getadjustedkwdata',
            'status' => $status,
            'message' => $message,
            'm2_total' => $total_adjusted_kw,
            'kw_total' => $adjusted_kw_sum,
            'total_price' => round($total_price, 2),
            'sales_tax_amount' => round($sales_tax_amount, 2),
            'sales_tax_per' => $sales_tax_per,
            'total_price_without_tex' => round($total_price, 2),
            'total_price_with_tex' => round($grand_total, 2),
            'unique_pid_rack_price' => $unique_pid_rack_price,
            'unique_pid_discount_price' => $unique_pid_discount_price,
            'm2_rack_price' => $m2_rack_price,
            'm2_discount_price' => $m2_discount_price,
            'data' => $getdata,
        ], $status_code);

    }

    public static function flatInvoiceCreator($invoice_no)
    {
        try {
            DB::beginTransaction();
            $controller = new self;
            $controller->checkSubscription(); // Check subscriptions

            // Fetch active subscriptions
            $subscriptionList = Subscriptions::where('status', 1)->where('flat_subscription', 1)->get();

            foreach ($subscriptionList as $subscription) {
                $subscriptionEndDate = $subscription->end_date;
                $subscriptionStartDate = $subscription->start_date;
                $salesTaxPer = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0
                    ? $subscription->sales_tax_per
                    : 7.25;

                $lastMonthTotal = isset($subscription->amount) && $subscription->amount > 0
                    ? $subscription->amount
                    : 0.00;

                // Initialize credit amount
                $creditAmount = 0.00;

                $billingDate = Carbon::parse($subscriptionEndDate);
                $formattedYear = $billingDate->format('Y');
                $formattedMonth = $billingDate->format('m');

                // Fetch credit data
                $creditData = Credit::whereYear('month', $formattedYear)
                    ->whereMonth('month', $formattedMonth)
                    ->orWhere('month', $subscriptionEndDate)
                    ->first();

                if ($creditData) {
                    $creditAmount = $creditData->amount + $creditData->old_balance_credit;
                }

                // Calculate taxable amount
                $lastMonthTaxableAmount = max($lastMonthTotal - $creditAmount, 0);

                $minimumBilling = isset($subscription->minimum_billing) ? $subscription->minimum_billing : 0;

                // Determine used and balance credits
                if ($lastMonthTotal < $minimumBilling) {
                    $lastMonthTaxableAmount = $minimumBilling;
                    $usedCredit = 0;
                    $balanceCredit = $creditAmount;
                } elseif ($lastMonthTaxableAmount < $minimumBilling) {
                    $lastMonthTaxableAmount = $minimumBilling;
                    $usedCredit = $lastMonthTotal - $lastMonthTaxableAmount;
                    $balanceCredit = $creditAmount - $usedCredit;
                } else {
                    $balanceCredit = 0;
                    $usedCredit = $creditAmount;
                }

                // Update credit data if it exists
                if ($creditData) {
                    $creditData->used_credit = $usedCredit;
                    $creditData->balance_credit = $balanceCredit;
                    $creditData->save();
                }

                // Amount calculation
                $salesTaxAmount = ($lastMonthTaxableAmount * $salesTaxPer) / 100;
                $grandLastMonthTotal = $lastMonthTotal + $salesTaxAmount;

                // Create Billing History
                $datah = [
                    'subscription_id' => $subscription->id,
                    'plan_id' => $subscription->plan_id,
                    'invoice_no' => $invoice_no,
                    'amount' => round($grandLastMonthTotal, 2),
                    'm2_rack_price' => 0,
                    'plan_name' => 'Sequifi',
                    'm2_discount_price' => 0,
                    'billing_date' => $subscription->end_date,
                ];

                $currentDate = Carbon::now();
                $profiledata = CompanyProfile::where('id', 1)->first();
                $billing_frequency = (isset($profiledata->frequency_type_id) && ! empty($profiledata->frequency_type_id)) ? $profiledata->frequency_type_id : 1; // default monthly
                if ($billing_frequency == 5) {
                    $endDay = $currentDate->endOfWeek()->toDateString();
                } else {
                    $endDay = $currentDate->endOfMonth()->toDateString();
                }
                $endDay = $currentDate->endOfMonth()->toDateString();
                $subscriptionBillingHistory = null;

                // Create billing history only if end date is not the last day of the month
                if ($subscriptionEndDate !== $endDay) {
                    $subscriptionBillingHistory = SubscriptionBillingHistory::create($datah);
                    $billingHistoryId = $subscriptionBillingHistory->id;
                }

                // Create New Subscription for the Next Month
                if (! empty($subscriptionBillingHistory)) {
                    $updateSubscription = Subscriptions::find($subscription->id);
                    $endDate = Carbon::parse($updateSubscription->end_date);
                    $newStartDate = $endDate->copy()->addDay()->startOfMonth();
                    $newEndDate = $newStartDate->endOfMonth()->toDateString(); // use endOfMonth() for correct date

                    // Update subscription details
                    $updateSubscription->total_m2 = 0;
                    $updateSubscription->sales_tax_per = $salesTaxPer;
                    $updateSubscription->status = 0; // Mark as inactive or complete
                    $updateSubscription->sales_tax_amount = $salesTaxAmount;
                    $updateSubscription->amount = round($lastMonthTotal, 2);
                    $updateSubscription->credit_amount = $creditAmount;
                    $updateSubscription->used_credit = $usedCredit;
                    $updateSubscription->balance_credit = $balanceCredit;
                    $updateSubscription->taxable_amount = round($lastMonthTaxableAmount, 2);
                    $updateSubscription->grand_total = round($grandLastMonthTotal, 2);

                    if ($updateSubscription->save()) {
                        // Create or update credit for the next month
                        if ($balanceCredit > 0) {
                            Credit::updateOrCreate(
                                ['month' => $newEndDate],
                                [
                                    'old_balance_credit' => $balanceCredit,
                                    'month' => $newEndDate,
                                ]
                            );
                        }
                    }
                }
            }
            DB::commit();
            $controller->autoallinvoicepay($invoice_no);
        } catch (\Exception $e) {
            DB::rollBack();
            log::info([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    public function checksubscription()
    {
        try {
            DB::beginTransaction();
            $plan_info = $this->addplanan();
            if ($plan_info) {
                $profiledata = CompanyProfile::where('id', 1)->first();
                $billing_frequency = (isset($profiledata->frequency_type_id) && ! empty($profiledata->frequency_type_id)) ? $profiledata->frequency_type_id : 1;
                $currentDate = Carbon::now();
                if ($billing_frequency == 5) {
                    $startDate = $currentDate->startOfWeek()->toDateString();
                    $endDate = $currentDate->endOfWeek()->toDateString();
                    $checkSCSubscription = Subscriptions::where('status', 1)->where('plan_id', $plan_info->id)->where('status', 1)->first();
                } else {
                    $startDate = $currentDate->startOfMonth()->toDateString();
                    $endDate = $currentDate->endOfMonth()->toDateString();
                    $checkSCSubscription = Subscriptions::where('status', 1)->where('plan_id', $plan_info->id)->where('start_date', $startDate)->where('end_date', $endDate)->first();
                }

                $sales_tax_per = $checkSCSubscription ? $checkSCSubscription->sales_tax_per : 7.25;
                $amount = $plan_info->unique_pid_rack_price ?? 0;

                $sales_tax_amount = (($amount * $sales_tax_per) / 100);
                $grand_total = ($amount + $sales_tax_amount);
                if ($checkSCSubscription) {
                    $update['sales_tax_amount'] = $sales_tax_amount;
                    $update['amount'] = $amount;
                    $update['grand_total'] = $grand_total;
                    $checkSCSubscription->update($update);
                } else {
                    $newSubsscription = new Subscriptions;
                    $newSubsscription->plan_type_id = 1;
                    $newSubsscription->plan_id = $plan_info->id;
                    $newSubsscription->start_date = $startDate;
                    $newSubsscription->end_date = $endDate;
                    $newSubsscription->status = 1;
                    $newSubsscription->sales_tax_per = $sales_tax_per;
                    $newSubsscription->sales_tax_amount = $sales_tax_amount;
                    $newSubsscription->amount = $amount;
                    $newSubsscription->grand_total = $grand_total;
                    $newSubsscription->flat_subscription = 1;
                    $newSubsscription->save();
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            log::info([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

    }

    public function addplanan()
    {
        $profiledata = CompanyProfile::where('id', 1)->first();
        if ($profiledata && $profiledata->is_flat) {
            $planname = 'Seqifi';
            $planData = Plans::where('id', 8)->first();
            if ($planData) {
                $planData->update([
                    'unique_pid_rack_price' => $profiledata->fixed_amount ?? 0.00,
                ]);
            } else {
                $planname = 'Monthly Flat Billing';
                $planData = new Plans;
                $planData->id = 8;
                $planData->name = $planname;
                $planData->product_name = 'Sequifi';
                $planData->unique_pid_rack_price = $profiledata->fixed_amount ?? 0.00;
                $planData->save();
            }

            return $planData;
        }

        return null;
    }

    public function stripePayInvoice(Request $request): JsonResponse
    {
        $invoiceId = openssl_decrypt($request->invoice_id, config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
        $payment_type = $request->payment_type;
        $type = config('services.stripe.type', 'test');
        $confirmPayment = 0;
        $controller = new self;
        $invoice = $controller->Invoicesinfo($type, $invoiceId);
        if (! empty($invoice) && isset($invoice['id']) && ! empty($invoice['id'])) {
            $paymentMethodId = $request->payment_method;
            $paymentIntentId = $invoice['payment_intent'];
            $customerId = $invoice['customer'];

            if ($payment_type == 'new') {
                // Get the fingerprint of new payment method
                $newCardFingerprint = $this->stripeGetNewPaymentFingerprint($paymentMethodId, $type);

                // check existing payment method fingerprint to new one
                $attachCard = 1;
                if (! empty($newCardFingerprint)) {
                    // $data['limit'] = 25;
                    // $existingPaymentLists = $this->stripegetpaymentlist($customerId,$data,$type);
                    // if(isset($existingPaymentLists['data']) && !empty($existingPaymentLists['data'])){
                    //     foreach ($existingPaymentLists['data'] as $existingCard) {
                    //         if ($existingCard[$existingCard['type']]['fingerprint'] === $newCardFingerprint) {
                    //             $paymentMethodId = $existingCard['id'];
                    //             $attachCard = 0;
                    //             $confirmPayment = 1;
                    //         }else{
                    //             $attachCard = 1;
                    //         }
                    //     }
                    // }
                    $existingPaymentLists = $this->getpaymentlist();
                    $payment_list = [];
                    if (isset($existingPaymentLists['payment_list']) && ! empty($existingPaymentLists['payment_list'])) {
                        $payment_list = $existingPaymentLists['payment_list'];
                        $found = false;
                        foreach ($payment_list as $item) {
                            if (isset($item['fingerprint']) && ! empty($item['fingerprint']) && ($item['fingerprint'] === $newCardFingerprint)) {
                                $attachCard = 0;
                                $found = true;
                                $paymentMethodId = $item['payment_id'];
                                $confirmPayment = 1;
                                break;
                            }
                        }
                    } else {
                        $attachCard = 1;
                    }
                }

                // attach new payment to customer
                if ($attachCard == 1) {
                    $attachPay = $this->stripeAttachNewPaymentToCustomer($paymentMethodId, ['customer' => $customerId], $type);
                    if (isset($attachPay['id']) && ! empty($attachPay['id'])) {
                        $confirmPayment = 1;
                    }
                }
            } else {
                $confirmPayment = 1;
            }

            if ($confirmPayment == 1) {
                // confirm payment
                $confirmPayment = $this->stripeConfirmPayment($paymentIntentId, ['payment_method' => $paymentMethodId], $type);
                if (! empty($confirmPayment)) {
                    if (isset($confirmPayment['id']) && ! empty($confirmPayment['id'])) {
                        SubscriptionBillingHistory::where('client_secret', $invoiceId)->update(['paid_status' => 3]);
                        if ($confirmPayment['status'] == 'succeeded') {
                            return response()->json([
                                'ApiName' => 'stripePayInvoice',
                                'status' => true,
                                'message' => 'Payment success',
                            ], 200);
                        } else {
                            return response()->json([
                                'ApiName' => 'stripePayInvoice',
                                'status' => true,
                                'message' => 'next_step',
                                'next_step' => $confirmPayment['status'],
                            ], 200);
                        }
                    } elseif (isset($confirmPayment['error']) && ! empty($confirmPayment['error'])) {
                        return response()->json([
                            'ApiName' => 'stripePayInvoice',
                            'status' => false,
                            'message' => $confirmPayment['error']['message'],
                        ], 400);
                    } else {
                        return response()->json([
                            'ApiName' => 'stripePayInvoice',
                            'status' => false,
                            'message' => 'Something went wrong, please try again later',
                        ], 400);
                    }
                } else {
                    return response()->json([
                        'ApiName' => 'stripePayInvoice',
                        'status' => false,
                        'message' => 'Payment Intent not found',
                    ], 400);
                }
            }
        } else {
            return response()->json([
                'ApiName' => 'stripePayInvoice',
                'status' => false,
                'message' => 'Invoice not found',
            ], 400);
        }
    }

    protected function stripeGetNewPaymentFingerprint($paymentMethodId, $type)
    {
        try {
            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }

            $url = "https://api.stripe.com/v1/payment_methods/$paymentMethodId";

            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, [], $headers, 'GET');
            $newPaymentMethod = json_decode($curl_response, true);
            $newCardFingerprint = $newPaymentMethod[$newPaymentMethod['type']]['fingerprint'] ?? null;

            return $newCardFingerprint;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripeAttachNewPaymentToCustomer($paymentMethodId, $data, $type)
    {
        try {
            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }

            $url = "https://api.stripe.com/v1/payment_methods/$paymentMethodId/attach";

            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $response = json_decode($curl_response, true);

            return $response;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    protected function stripeconfirmpayment($paymentIntentId, $data, $type)
    {
        try {
            if ($type == 'live') {
                // $stripe_key = config('services.stripe.key_live');
                $stripe_key = openssl_decrypt(config('services.stripe.key_live'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            } else {
                // $stripe_key = config('services.stripe.key_test');
                $stripe_key = openssl_decrypt(config('services.stripe.key_test'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            }

            $url = "https://api.stripe.com/v1/payment_intents/$paymentIntentId/confirm";

            $headers = [
                'accept: application/json, text/plain, */*',

                'authorization: Bearer '.$stripe_key, // live
                // 'authorization: Bearer '.config('services.stripe.key_test'), //test
                'content-type: application/x-www-form-urlencoded',
            ];
            $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
            $response = json_decode($curl_response, true);
            Log::info('stripeconfirmpayment '.print_r($response, true));

            return $response;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    public function sendInvoiceToMail($data, $invoice_no, $stripe_invoice_id, $type)
    {
        try {
            $controller = new self;
            $invoice = $controller->Invoicesinfo('test', $stripe_invoice_id);
            $mailData = [
                'email' => $data->company_email,
                'subject' => 'New invoice from '.$invoice['account_name'].' #'.$invoice['number'],
                'template' => view('mail.stripe_sequifi_invoice', ['invoice_no' => $invoice_no, 'invoice' => $invoice]),
            ];

            $client = new Client;
            $response = $client->get($invoice['invoice_pdf']);
            $pdfPath = storage_path('app/public/'.$stripe_invoice_id.time().'.pdf');
            file_put_contents($pdfPath, $response->getBody());
            $mailData['pdfPath'] = $pdfPath;
            $mail = $this->sendEmailWithAttachment($mailData);

            // Delete the file after sending
            unlink($pdfPath);
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage().' => '.$th->getLine();
            createLogFile('stripe', " error \n");
            createLogFile('stripe', $errorMessage);
        }

    }

    // to run cron throough api
    public function runBillingCommand()
    {
        Artisan::call('genratebilling:history');
    }

    public function checkDuplicateAndAttachToCustomer($paymentMethodId): JsonResponse
    {
        $type = config('services.stripe.type', 'test');
        $profiledata = CompanyProfile::where('id', 1)->first();
        $customerId = $profiledata->stripe_customer_id;
        // Get the fingerprint of new payment method
        $newCardFingerprint = $this->stripeGetNewPaymentFingerprint($paymentMethodId, $type);

        // check existing payment method fingerprint to new one
        $attachCard = 1;
        if (! empty($newCardFingerprint)) {
            $existingPaymentLists = $this->getpaymentlist();
            $payment_list = [];
            if (isset($existingPaymentLists['payment_list'])) {
                $payment_list = $existingPaymentLists['payment_list'];
                $found = false;
                foreach ($payment_list as $item) {
                    if (isset($item['fingerprint']) && ! empty($item['fingerprint']) && ($item['fingerprint'] === $newCardFingerprint)) {
                        $attachCard = 0;
                        $found = true;
                        break;
                    }
                }
            }
        }

        // attach new payment to customer
        if ($attachCard == 1) {
            $attachPay = $this->stripeAttachNewPaymentToCustomer($paymentMethodId, ['customer' => $customerId], $type);
            if (isset($attachPay['id']) && ! empty($attachPay['id'])) {
                return response()->json([
                    'ApiName' => 'checkDuplicateAndAttachToCustomer',
                    'status' => true,
                    'message' => 'Payment method attached to the custmer',
                    'data' => $attachPay,
                ], 200);
            } elseif (isset($attachPay['error']) && ! empty($attachPay['error'])) {
                return response()->json([
                    'ApiName' => 'checkDuplicateAndAttachToCustomer',
                    'status' => true,
                    'message' => $attachPay['error']['message'],
                    'data' => $attachPay,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'checkDuplicateAndAttachToCustomer',
                    'status' => false,
                    'message' => 'Error in attaching payment method to customer',
                    'data' => $attachPay,
                ], 400);
            }

        } else {
            return response()->json([
                'ApiName' => 'checkDuplicateAndAttachToCustomer',
                'status' => false,
                'message' => 'This payment details already exist',
            ], 400);
        }
    }
}
