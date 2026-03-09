<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\StripeBillingController;
use App\Models\Arena;
use App\Models\Buckets;
use App\Models\CompanyProfile;
use App\Models\Credit;
use App\Models\CrmSetting;
use App\Models\SalesInvoiceDetail;
use App\Models\SClearancePlan;
use App\Models\SClearanceScreeningRequestList;
use App\Models\SequiaiPlan;
use App\Models\SequiaiRequestHistory;
use App\Models\SubscriptionBillingHistory;
use App\Models\Subscriptions;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenrateWeeklyBillingHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'genrateWeeklyBilling:history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Function for Genrate Stripe Billing History Weekly';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $profiledata = CompanyProfile::where('id', 1)->first();
        $billing_frequency = (isset($profiledata->frequency_type_id) && ! empty($profiledata->frequency_type_id)) ? $profiledata->frequency_type_id : 1; // default monthly

        if ($billing_frequency == 5) {
            // create_billing_history from subscription
            $credit_amount = 0;
            $balance_credit = 0;
            $used_credit = 0;
            try {
                $profiledata = CompanyProfile::where('id', 1)->first();
                if (isset($profiledata->is_flat) && $profiledata->is_flat == 1) {
                    $invoice_no = SubscriptionBillingHistory::genrate_invoice();
                    StripeBillingController::flatinvoicecreator($invoice_no);
                    exit;
                }
                DB::beginTransaction();

                $invoice_no = SubscriptionBillingHistory::genrate_invoice();

                $subscription_last = Subscriptions::with('plans', 'billingType')
                    ->whereHas('billingType', function ($query) {
                        $query->where('id', 5);
                    })->where('status', 1)->where('flat_subscription', 0)->get();

                if (count($subscription_last) > 0) {
                    $SubscriptionBillingHistory = [];
                    foreach ($subscription_last as $subscription_last) {
                        $plan_id = isset($subscription_last->plans) ? $subscription_last->plans->id : null;
                        $plan_name = isset($subscription_last->plans) ? $subscription_last->plans->name : null;
                        $product_name = isset($subscription_last->plans) ? $subscription_last->plans->product_name : null;
                        $unique_pid_rack_price = isset($subscription_last->plans) ? $subscription_last->plans->unique_pid_rack_price : 0;
                        $unique_pid_discount_price = isset($subscription_last->plans) ? $subscription_last->plans->unique_pid_discount_price : 0;
                        $minimum_billing = isset($subscription_last->minimum_billing) ? $subscription_last->minimum_billing : 0;
                        $credit_amount = 0;

                        $m2_rack_price = isset($subscription_last->plans) ? $subscription_last->plans->m2_rack_price : 0;
                        $m2_discount_price = isset($subscription_last->plans) ? $subscription_last->plans->m2_discount_price : 0;

                        $permars_array = [];
                        $subscription_end_date = $subscription_last->end_date;
                        $subscription_start_date = $subscription_last->start_date;
                        // Get data for invoice
                        $permars_array['subscription_end_date'] = $subscription_end_date;
                        $permars_array['subscription_start_date'] = $subscription_start_date;

                        $billingDate = Carbon::parse($subscription_end_date);
                        $formattedYear = $billingDate->format('Y');
                        $formattedMonth = $billingDate->format('m');

                        if ($plan_id == 1) {
                            DB::beginTransaction();
                            $unique_pids_m2_date_datas = SalesInvoiceDetail::unique_pids_m2_date_datas($permars_array);
                            $last_month_pid_data = $unique_pids_m2_date_datas['unique_pid_data'];
                            $last_month_m2_data = $unique_pids_m2_date_datas['m2_date_data'];

                            $last_month_pid_count = count($last_month_pid_data);
                            $last_pid_kw_sum = round(array_sum(array_column($last_month_pid_data, 'kw')), 2);

                            $last_month_m2_pid_count = count($last_month_m2_data);
                            $last_month_m2_kw_sum = round(array_sum(array_column($last_month_m2_data, 'kw')), 2);

                            $kw_adjusted_total_price = 0;
                            $kw_adjusted_total_count = 0;
                            $kw_adjusted_all_pids = [];
                            $kw_adjusted_data = [];
                            if (isset($unique_pids_m2_date_datas['kw_adjusted_data']) && ! empty($unique_pids_m2_date_datas['kw_adjusted_data'])) {
                                $kw_adjusted_data = $unique_pids_m2_date_datas['kw_adjusted_data'];
                                $kw_adjusted_total_count = count($kw_adjusted_data);
                                $kw_adjusted_all_pids = $unique_pids_m2_date_datas['kw_adjusted_all_pids'];
                                $kw_adjusted_sum = round(array_sum(array_column($kw_adjusted_data, 'kw_diff')), 2);
                                $kw_adjusted_total_price = $kw_adjusted_sum * 1000 * $m2_discount_price;
                            }

                            $total_pids_for_invoice = $last_month_pid_count + $last_month_m2_pid_count + $kw_adjusted_total_count;

                            // Pid calculation
                            $last_mont_pid_total_amount = $last_pid_kw_sum * 1000 * $unique_pid_discount_price;

                            // m2 date calculation
                            $last_month_m2_total_amount = $last_month_m2_kw_sum * 1000 * $m2_discount_price;

                            // sales tax per
                            $sales_tax_per = isset($subscription_last->sales_tax_per) && $subscription_last->sales_tax_per > 0 ? $subscription_last->sales_tax_per : 7.25;

                            // calculat total tax and other amount.
                            $last_month_total = ($last_mont_pid_total_amount + $last_month_m2_total_amount + $kw_adjusted_total_price);

                            $credit_data = Credit::whereYear('month', $formattedYear)->whereMonth('month', $formattedMonth)->orWhere('month', $subscription_end_date)->first();
                            if ($credit_data) {
                                $credit_amount = $credit_data->amount + $credit_data->old_balance_credit;
                            }
                            $last_month_taxable_amount = $last_month_total - $credit_amount;

                            if ($last_month_total < $minimum_billing) {
                                $last_month_taxable_amount = $minimum_billing;
                                $used_credit = 0;
                                $balance_credit = $credit_amount;
                            } elseif ($last_month_taxable_amount < $minimum_billing) {
                                $last_month_taxable_amount = $minimum_billing;
                                $used_credit = $last_month_total - $last_month_taxable_amount;
                                $balance_credit = $credit_amount - $used_credit;
                            } else {
                                $balance_credit = 0;
                                $used_credit = $credit_amount;
                            }

                            if ($credit_data) {
                                $credit_data->used_credit = $used_credit;
                                $credit_data->balance_credit = $balance_credit;
                                $credit_data->save();
                            }

                            $sales_tax_amount = (($last_month_taxable_amount * $sales_tax_per) / 100);
                            $grand_last_month_total = ($last_month_taxable_amount + $sales_tax_amount);

                            // genrate invoice Number
                            // $invoice_no = SubscriptionBillingHistory::genrate_invoice();
                            $billing_date = $subscription_last->end_date;

                            // Creating Billing History
                            $datah = [
                                'subscription_id' => $subscription_last->id,
                                'invoice_no' => $invoice_no,
                                'amount' => round($grand_last_month_total, 2),
                                'plan_id' => $plan_id,
                                'plan_name' => $plan_name,
                                'unique_pid_rack_price' => $unique_pid_rack_price,
                                'unique_pid_discount_price' => $unique_pid_discount_price,
                                'm2_rack_price' => $m2_rack_price,
                                'm2_discount_price' => $m2_discount_price,
                                'billing_date' => $subscription_last->end_date,
                            ];
                            $SubscriptionBillingHistory = SubscriptionBillingHistory::Create($datah);
                            $billing_history_id = $SubscriptionBillingHistory->id;

                            if ($SubscriptionBillingHistory) {
                                // Creating New subscription for next month

                                $update_subscription = Subscriptions::where('id', '=', $subscription_last->id)->first();
                                $endDate = Carbon::parse($update_subscription->end_date);
                                if ($billing_frequency == 5) {
                                    $currentDate = Carbon::now();
                                    $newstartDate = $endDate->addDay()->startOfWeek()->toDateString();
                                    $newEndDate = $endDate->addDay()->endOfWeek()->toDateString();
                                } else {
                                    $newstartDate = $endDate->copy()->addDay()->startOfMonth();
                                    $newEndDate = date('Y-m-t', strtotime($newstartDate));
                                }

                                // Creating New subscription for next month Data
                                $create_subscription = [
                                    'plan_id' => $update_subscription->plan_id,
                                    'plan_type_id' => $update_subscription->plan_type_id,
                                    'sales_tax_per' => isset($update_subscription->sales_tax_per) && $update_subscription->sales_tax_per > 0 ? $update_subscription->sales_tax_per : 7.25,
                                    'start_date' => $newstartDate,
                                    'end_date' => $newEndDate,
                                    'minimum_billing' => $minimum_billing,
                                    'status' => 1,
                                ];

                                $update_subscription->total_pid = $last_month_pid_count;
                                $update_subscription->total_m2 = $last_month_m2_pid_count;
                                $update_subscription->sales_tax_per = $sales_tax_per;
                                $update_subscription->status = 0;
                                $update_subscription->sales_tax_amount = round($sales_tax_amount, 2);
                                $update_subscription->amount = $last_month_total;
                                $update_subscription->credit_amount = $credit_amount;
                                $update_subscription->used_credit = $used_credit;
                                $update_subscription->balance_credit = $balance_credit;
                                $update_subscription->taxable_amount = $last_month_taxable_amount;
                                $update_subscription->grand_total = round($grand_last_month_total, 2);
                                $update_status = $update_subscription->update();
                                if ($update_status) {
                                    // strore pid_data
                                    foreach ($last_month_pid_data as $pid_data_row) {
                                        $insert_SalesInvoiceDetail = new SalesInvoiceDetail;
                                        $insert_SalesInvoiceDetail->pid = $pid_data_row['pid'];
                                        $insert_SalesInvoiceDetail->sale_master_id = $pid_data_row['id'];
                                        $insert_SalesInvoiceDetail->data_from = $pid_data_row['data_from'];
                                        $insert_SalesInvoiceDetail->customer_name = $pid_data_row['customer_name'];
                                        $insert_SalesInvoiceDetail->customer_state = $pid_data_row['customer_state'];
                                        $insert_SalesInvoiceDetail->kw = $pid_data_row['kw'];
                                        $insert_SalesInvoiceDetail->customer_signoff = $pid_data_row['customer_signoff'];
                                        $insert_SalesInvoiceDetail->m1_date = $pid_data_row['m1_date'];
                                        $insert_SalesInvoiceDetail->m2_date = $pid_data_row['m2_date'];
                                        $insert_SalesInvoiceDetail->invoice_for = 'unique_pid';
                                        $insert_SalesInvoiceDetail->billing_history_id = $billing_history_id;
                                        $insert_SalesInvoiceDetail->invoice_no = $invoice_no;
                                        $insert_SalesInvoiceDetail->billing_date = $billing_date;
                                        $insert_SalesInvoiceDetail->save();
                                    }
                                    // store m2 data
                                    foreach ($last_month_m2_data as $m2_data_row) {
                                        $insert_SalesInvoiceDetail = new SalesInvoiceDetail;
                                        $insert_SalesInvoiceDetail->pid = $m2_data_row['pid'];
                                        $insert_SalesInvoiceDetail->sale_master_id = $m2_data_row['id'];
                                        $insert_SalesInvoiceDetail->data_from = $m2_data_row['data_from'];
                                        $insert_SalesInvoiceDetail->customer_name = $m2_data_row['customer_name'];
                                        $insert_SalesInvoiceDetail->customer_state = $m2_data_row['customer_state'];
                                        $insert_SalesInvoiceDetail->kw = $m2_data_row['kw'];
                                        $insert_SalesInvoiceDetail->customer_signoff = $m2_data_row['customer_signoff'];
                                        $insert_SalesInvoiceDetail->m1_date = $m2_data_row['m1_date'];
                                        $insert_SalesInvoiceDetail->m2_date = $m2_data_row['m2_date'];
                                        $insert_SalesInvoiceDetail->invoice_for = 'm2_date';
                                        $insert_SalesInvoiceDetail->billing_history_id = $billing_history_id;
                                        $insert_SalesInvoiceDetail->invoice_no = $invoice_no;
                                        $insert_SalesInvoiceDetail->billing_date = $billing_date;
                                        if (! empty($kw_adjusted_all_pids) && in_array($m2_data_row['pid'], $kw_adjusted_all_pids)) {
                                            $insert_SalesInvoiceDetail->is_kw_adjusted_invoice = 1;
                                        }
                                        $insert_SalesInvoiceDetail->save();
                                    }

                                    // store kw adjusted data
                                    if (! empty($kw_adjusted_data)) {
                                        foreach ($kw_adjusted_data as $kw_data_row) {
                                            $insert_SalesInvoiceDetail = new SalesInvoiceDetail;
                                            $insert_SalesInvoiceDetail->pid = $kw_data_row['pid'];
                                            $insert_SalesInvoiceDetail->sale_master_id = $kw_data_row['id'];
                                            $insert_SalesInvoiceDetail->data_from = $kw_data_row['data_from'];
                                            $insert_SalesInvoiceDetail->customer_name = $kw_data_row['customer_name'];
                                            $insert_SalesInvoiceDetail->customer_state = $kw_data_row['customer_state'];
                                            $insert_SalesInvoiceDetail->kw = $kw_data_row['updated_kw'];
                                            $insert_SalesInvoiceDetail->customer_signoff = $kw_data_row['customer_signoff'];
                                            $insert_SalesInvoiceDetail->m1_date = $kw_data_row['m1_date'];
                                            $insert_SalesInvoiceDetail->m2_date = $kw_data_row['m2_date'];
                                            $insert_SalesInvoiceDetail->invoice_for = 'm2_date';
                                            $insert_SalesInvoiceDetail->billing_history_id = $billing_history_id;
                                            $insert_SalesInvoiceDetail->invoice_no = $invoice_no;
                                            $insert_SalesInvoiceDetail->billing_date = $billing_date;

                                            if (! empty($kw_adjusted_all_pids) && in_array($kw_data_row['pid'], $kw_adjusted_all_pids)) {
                                                $insert_SalesInvoiceDetail->is_kw_adjusted_invoice = 1;
                                                $insert_SalesInvoiceDetail->invoice_generated_on_kw = $kw_data_row['kw_diff'];
                                            }
                                            $insert_SalesInvoiceDetail->save();
                                        }
                                    }

                                    $SalesInvoiceDetail_count = SalesInvoiceDetail::where('billing_history_id', '=', $billing_history_id)->count();
                                    $new_subscription_count = Subscriptions::where('plan_id', $plan_id)->whereDate('start_date', '=', $newstartDate)->whereDate('end_date', '=', $newEndDate)->where('status', 1)->orderby('subscriptions.id', 'desc')->count();
                                    if ($new_subscription_count == 0 && $SalesInvoiceDetail_count == $total_pids_for_invoice) {

                                        // if exists next month subscription then update otherwise create
                                        $subscription_next = Subscriptions::where(['status' => 2])->whereIn('plan_id', [1, 5])->whereDate('start_date', '>', $subscription_last->end_date)->first();

                                        if (! empty($subscription_next)) {
                                            // Creating New subscription for next month
                                            $Subscriptions = Subscriptions::where(['id' => $subscription_next->id])->update(['status' => 1]);
                                        } else {
                                            $current_billing = function_exists('tenant') && tenant() ? tenant()->base_fee : null;
                                            if ($current_billing == 'per_pid_and_m2') {
                                                // Creating New subscription for next month
                                                $Subscriptions = Subscriptions::Create($create_subscription);
                                            }
                                        }
                                    }

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
                                    DB::rollBack();
                                }
                            }
                        } elseif ($plan_id == 3) {
                            DB::beginTransaction();
                            $permars_array['subscription_start_date'] = $subscription_start_date;
                            $everee_billing = SalesInvoiceDetail::everee_billing($permars_array);

                            $payroll_histry_id_data = $everee_billing['payroll_histry_id_data'];
                            $one_time_payment_date_data = $everee_billing['one_time_payment_date_data'];

                            $last_month_payroll_histry_count = count($payroll_histry_id_data);
                            $last_payroll_histry_net_pay_sum = array_sum(array_column($payroll_histry_id_data, 'net_pay'));

                            $last_month_one_time_payment_count = count($one_time_payment_date_data);
                            $last_one_time_payment_net_pay_sum = array_sum(array_column($one_time_payment_date_data, 'net_pay'));

                            $total_payment_data_for_invoice = $last_month_payroll_histry_count + $last_month_one_time_payment_count;

                            // Pid calculation
                            $last_month_payroll_total_amount = $last_month_payroll_histry_count * $unique_pid_discount_price;

                            // m2 date calculation
                            $last_month_otp_total_amount = $last_month_one_time_payment_count * $m2_discount_price;

                            // sales tax per
                            $sales_tax_per = isset($subscription_last->sales_tax_per) && $subscription_last->sales_tax_per > 0 ? $subscription_last->sales_tax_per : 7.25;

                            // calculat total tax and other amount.
                            $last_month_total = ($last_month_payroll_total_amount + $last_month_otp_total_amount);

                            $sales_tax_amount = (($last_month_total * $sales_tax_per) / 100);
                            $grand_last_month_total = ($last_month_total + $sales_tax_amount);

                            $billing_date = $subscription_last->end_date;

                            // Creating Billing History
                            $datah = [
                                'subscription_id' => $subscription_last->id,
                                'invoice_no' => $invoice_no,
                                'amount' => round($grand_last_month_total, 2),
                                'plan_id' => $plan_id,
                                'plan_name' => $plan_name,
                                'unique_pid_rack_price' => $unique_pid_rack_price,
                                'unique_pid_discount_price' => $unique_pid_discount_price,
                                'm2_rack_price' => $m2_rack_price,
                                'm2_discount_price' => $m2_discount_price,
                                'billing_date' => $subscription_last->end_date,
                            ];
                            $evereeSubscriptionBillingHistory = SubscriptionBillingHistory::Create($datah);
                            $billing_history_id = $evereeSubscriptionBillingHistory->id;

                            if ($evereeSubscriptionBillingHistory) {
                                // Creating New subscription for next month

                                $update_subscription = Subscriptions::where('id', '=', $subscription_last->id)->first();
                                $endDate = Carbon::parse($update_subscription->end_date);
                                if ($billing_frequency == 5) {
                                    $currentDate = Carbon::now();
                                    $newstartDate = $endDate->addDay()->startOfWeek()->toDateString();
                                    $newEndDate = $endDate->addDay()->endOfWeek()->toDateString();
                                } else {
                                    $newstartDate = $endDate->copy()->addDay()->startOfMonth();
                                    $newEndDate = date('Y-m-t', strtotime($newstartDate));
                                }

                                // Creating New subscription for next month Data
                                $create_subscription = [
                                    'plan_id' => $update_subscription->plan_id,
                                    'plan_type_id' => $update_subscription->plan_type_id,
                                    'sales_tax_per' => isset($update_subscription->sales_tax_per) && $update_subscription->sales_tax_per > 0 ? $update_subscription->sales_tax_per : 0,
                                    'start_date' => $newstartDate,
                                    'end_date' => $newEndDate,
                                    'minimum_billing' => $minimum_billing,
                                    'status' => 1,
                                ];

                                $update_subscription->total_pid = $last_month_payroll_histry_count;
                                $update_subscription->total_m2 = $last_month_one_time_payment_count;
                                $update_subscription->sales_tax_per = $sales_tax_per;
                                $update_subscription->status = 0;
                                $update_subscription->sales_tax_amount = round($sales_tax_amount, 2);
                                $update_subscription->amount = $last_month_total;
                                $update_subscription->credit_amount = 0; // $credit_amount;
                                $update_subscription->used_credit = 0; // $used_credit;
                                $update_subscription->balance_credit = 0; // $balance_credit;
                                $update_subscription->taxable_amount = $last_month_total;
                                $update_subscription->grand_total = round($grand_last_month_total, 2);
                                $update_status = $update_subscription->update();
                                if ($update_status) {
                                    // strore payroll history data
                                    foreach ($payroll_histry_id_data as $pid_data_row) {
                                        $insert_SalesInvoiceDetail = new SalesInvoiceDetail;
                                        $insert_SalesInvoiceDetail->pid = $pid_data_row['everee_paymentId'];
                                        $insert_SalesInvoiceDetail->sale_master_id = $pid_data_row['id'];
                                        $insert_SalesInvoiceDetail->data_from = $pid_data_row['created_at'];
                                        $insert_SalesInvoiceDetail->customer_name = $pid_data_row['usersdata']['first_name'] . ' ' . $pid_data_row['usersdata']['last_name'];
                                        $insert_SalesInvoiceDetail->customer_state = $pid_data_row['everee_external_id'];
                                        $insert_SalesInvoiceDetail->kw = $pid_data_row['net_pay'];
                                        $insert_SalesInvoiceDetail->customer_signoff = $pid_data_row['everee_payment_requestId'];
                                        $insert_SalesInvoiceDetail->m1_date = $pid_data_row['pay_period_from'];
                                        $insert_SalesInvoiceDetail->m2_date = $pid_data_row['pay_period_to'];
                                        $insert_SalesInvoiceDetail->invoice_for = 'payroll_histry';
                                        $insert_SalesInvoiceDetail->billing_history_id = $billing_history_id;
                                        $insert_SalesInvoiceDetail->invoice_no = $invoice_no;
                                        $insert_SalesInvoiceDetail->billing_date = $billing_date;
                                        $insert_SalesInvoiceDetail->save();
                                    }
                                    // store one time payment data
                                    foreach ($one_time_payment_date_data as $otp_row) {
                                        $insert_SalesInvoiceDetail = new SalesInvoiceDetail;
                                        $insert_SalesInvoiceDetail->pid = $otp_row['everee_paymentId'];
                                        $insert_SalesInvoiceDetail->sale_master_id = $otp_row['id'];
                                        $insert_SalesInvoiceDetail->data_from = $otp_row['created_at'];
                                        $insert_SalesInvoiceDetail->customer_name = $otp_row['usersdata']['first_name'] . ' ' . $otp_row['usersdata']['last_name'];
                                        $insert_SalesInvoiceDetail->customer_state = $otp_row['everee_external_id'];
                                        $insert_SalesInvoiceDetail->kw = $otp_row['net_pay'];
                                        $insert_SalesInvoiceDetail->customer_signoff = $otp_row['everee_payment_requestId'];
                                        $insert_SalesInvoiceDetail->m1_date = $otp_row['pay_period_from'];
                                        $insert_SalesInvoiceDetail->m2_date = $otp_row['pay_period_to'];
                                        $insert_SalesInvoiceDetail->invoice_for = 'one_time_paymment';
                                        $insert_SalesInvoiceDetail->billing_history_id = $billing_history_id;
                                        $insert_SalesInvoiceDetail->invoice_no = $invoice_no;
                                        $insert_SalesInvoiceDetail->billing_date = $billing_date;
                                        $insert_SalesInvoiceDetail->save();
                                    }
                                    $EvereeInvoiceDetail_count = SalesInvoiceDetail::where('billing_history_id', '=', $billing_history_id)->count();
                                    $new_subscription_count = Subscriptions::where('plan_id', $plan_id)->whereDate('start_date', '=', $newstartDate)->whereDate('end_date', '=', $newEndDate)->orderby('subscriptions.id', 'desc')->count();
                                    if ($new_subscription_count == 0 && $EvereeInvoiceDetail_count == $total_payment_data_for_invoice) {
                                        // Creating New subscription for next month
                                        $Subscriptions = Subscriptions::Create($create_subscription);
                                    }

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
                                    DB::rollBack();
                                }
                            }
                        } elseif ($plan_id == 2) { // S-Clearance
                            $this->SClearanceBilling($subscription_last, $invoice_no, $billing_frequency);
                        }
                        // elseif ($plan_id==4) {
                        //     $this->userWiseBilling($subscription_last, $invoice_no);
                        // }
                        elseif ($plan_id == 5) {
                            $this->userWiseBilling($subscription_last, $invoice_no, $billing_frequency);
                        } elseif ($plan_id == 4) {
                            // SequiAI
                            $this->SquiAiBilling($subscription_last, $invoice_no, $billing_frequency);
                        } elseif ($plan_id == 7) {
                            Arena::SequiArenaBilling($subscription_last, $invoice_no, $billing_frequency);
                        } elseif ($plan_id == 6) {
                            Buckets::SequiCRMBilling($subscription_last, $invoice_no, $billing_frequency);
                        }
                    }
                } else {

                    // exit('Subscription not found or not active!! Nothing for Invoice Genrate');
                    // return Command::SUCCESS;

                }
                DB::commit();
                StripeBillingController::autoallinvoicepay($invoice_no);
            } catch (\Exception $e) {
                DB::rollBack();
                log::info([
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }
    }

    /**
     * @method SClearanceBilling
     * Add data for S-Clearance billing
     */
    public function SClearanceBilling($subscription_last, $invoice_no, $billing_frequency)
    {
        DB::beginTransaction();
        $unique_pid_discount_price = 0;
        $m2_discount_price = 0;
        $balance_credit = 0;
        $used_credit = 0;
        $permars_array = [];
        $subscription_end_date = $subscription_last->end_date;
        $subscription_start_date = $subscription_last->start_date;
        $minimum_billing = isset($subscription_last->minimum_billing) ? $subscription_last->minimum_billing : 0;
        $credit_amount = 0;

        // sales tax per
        $sales_tax_per = isset($subscription_last->sales_tax_per) && $subscription_last->sales_tax_per > 0 ? $subscription_last->sales_tax_per : 7.25;

        // calculat total tax and other amount.
        $last_month_total = 0;
        $reportData = SClearanceScreeningRequestList::select('plan_id', DB::raw('COUNT(id) as reportCount'))->whereBetween('report_date', [$subscription_start_date, $subscription_end_date])->get()->toArray();
        if (! empty($reportData)) {
            $amount = 0;
            foreach ($reportData as $report) {
                $planData = SClearancePlan::select('price')->where('id', '=', $report['plan_id'])->get()->toarray();
                if (isset($planData[0]['price'])) {
                    $amount = $planData[0]['price'] * $report['reportCount'];
                    $last_month_total += $amount;
                }
            }
        }

        $billingDate = Carbon::parse($subscription_end_date);
        $formattedYear = $billingDate->format('Y');
        $formattedMonth = $billingDate->format('m');

        $credit_data = Credit::whereYear('month', $formattedYear)->whereMonth('month', $formattedMonth)->orWhere('month', $subscription_end_date)->first();
        if ($credit_data) {
            $credit_amount = $credit_data->amount + $credit_data->old_balance_credit;
        }

        $last_month_taxable_amount = $last_month_total - $credit_amount;

        if ($last_month_total < $minimum_billing) {
            $last_month_taxable_amount = $minimum_billing;
            $used_credit = 0;
            $balance_credit = $credit_amount;
        } elseif ($last_month_taxable_amount < $minimum_billing) {
            $last_month_taxable_amount = $minimum_billing;
            $used_credit = $last_month_total - $last_month_taxable_amount;
            $balance_credit = $credit_amount - $used_credit;
        } else {
            $balance_credit = 0;
            $used_credit = $credit_amount;
        }

        if ($credit_data) {
            $credit_data->used_credit = $used_credit;
            $credit_data->balance_credit = $balance_credit;
            $credit_data->save();
        }

        // amount calculation
        $last_month_taxable_amount = $last_month_total;
        $sales_tax_amount = (($last_month_taxable_amount * $sales_tax_per) / 100);
        $grand_last_month_total = ($last_month_total + $sales_tax_amount);
        $billing_date = $subscription_last->end_date;
        $plan_id = isset($subscription_last->plans) ? $subscription_last->plans->id : null;
        $plan_name = isset($subscription_last->plans) ? $subscription_last->plans->name : null;
        $product_name = isset($subscription_last->plans) ? $subscription_last->plans->product_name : null;

        // Creating Billing History
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

        $subscriptionBillingHistory = SubscriptionBillingHistory::Create($datah);
        $billing_history_id = $subscriptionBillingHistory->id;

        if ($subscriptionBillingHistory) {
            // Creating New subscription for next month

            $update_subscription = Subscriptions::where('id', '=', $subscription_last->id)->first();

            $endDate = Carbon::parse($update_subscription->end_date);
            if ($billing_frequency == 5) {
                $currentDate = Carbon::now();
                $newstartDate = $endDate->addDay()->startOfWeek()->toDateString();
                $newEndDate = $endDate->addDay()->endOfWeek()->toDateString();
            } else {
                $newstartDate = $endDate->copy()->addDay()->startOfMonth();
                $newEndDate = date('Y-m-t', strtotime($newstartDate));
            }

            // Creating New subscription for next month Data
            $create_subscription = [
                'plan_id' => $update_subscription->plan_id,
                'plan_type_id' => $update_subscription->plan_type_id,
                'sales_tax_per' => isset($update_subscription->sales_tax_per) && $update_subscription->sales_tax_per > 0 ? $update_subscription->sales_tax_per : 7.25,
                'start_date' => $newstartDate,
                'end_date' => $newEndDate,
                'minimum_billing' => $minimum_billing,
                'status' => 1,
            ];

            $update_subscription->total_pid = 0;
            $update_subscription->total_m2 = 0;
            $update_subscription->sales_tax_per = $sales_tax_per;
            $update_subscription->status = 0;
            $update_subscription->sales_tax_amount = $sales_tax_amount;
            $update_subscription->amount = round($last_month_total, 2);
            $update_subscription->credit_amount = $credit_amount;
            $update_subscription->used_credit = $used_credit;
            $update_subscription->balance_credit = $balance_credit;
            $update_subscription->taxable_amount = round($last_month_taxable_amount, 2);
            $update_subscription->grand_total = round($grand_last_month_total, 2);
            $update_status = $update_subscription->update();

            if ($update_status) {

                $new_subscription_count = Subscriptions::where('plan_id', $plan_id)->whereDate('start_date', '=', $newstartDate)->whereDate('end_date', '=', $newEndDate)->orderby('subscriptions.id', 'desc')->count();

                if ($new_subscription_count == 0) {
                    // Creating New subscription for next month
                    $Subscriptions = Subscriptions::Create($create_subscription);
                }

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
                DB::rollBack();
            }
        }
    }

    public function userWiseBilling($subscription_last, $invoice_no, $billing_frequency)
    {
        DB::beginTransaction();
        $plan_id = isset($subscription_last->plans) ? $subscription_last->plans->id : null;
        $plan_name = isset($subscription_last->plans) ? $subscription_last->plans->name : null;
        $product_name = isset($subscription_last->plans) ? $subscription_last->plans->product_name : null;
        $unique_pid_rack_price = isset($subscription_last->plans) ? $subscription_last->plans->unique_pid_rack_price : 0;
        $unique_pid_discount_price = isset($subscription_last->plans) ? $subscription_last->plans->unique_pid_discount_price : 0;
        $minimum_billing = isset($subscription_last->minimum_billing) ? $subscription_last->minimum_billing : 0;
        $credit_amount = 0;

        $m2_rack_price = isset($subscription_last->plans) ? $subscription_last->plans->m2_rack_price : 0;
        $m2_discount_price = isset($subscription_last->plans) ? $subscription_last->plans->m2_discount_price : 0;

        $permars_array = [];
        $subscription_end_date = $subscription_last->end_date;
        $subscription_start_date = $subscription_last->start_date;
        // Get data for invoice
        $permars_array['subscription_end_date'] = $subscription_end_date;
        $permars_array['subscription_start_date'] = $subscription_start_date;

        $billingDate = Carbon::parse($subscription_end_date);
        $formattedYear = $billingDate->format('Y');
        $formattedMonth = $billingDate->format('m');

        $users_billing = StripeBillingController::userWiseBillingData($subscription_last);

        $user_id_data = $users_billing['user_id_data'];
        // $one_time_payment_date_data = $users_billing['one_time_payment_date_data'];

        $last_month_payroll_histry_count = count($user_id_data);
        // $last_payroll_histry_net_pay_sum = array_sum(array_column($user_id_data, 'net_pay'));

        $last_month_one_time_payment_count = 0;
        // $last_one_time_payment_net_pay_sum = array_sum(array_column($one_time_payment_date_data, 'net_pay'));

        $total_payment_data_for_invoice = $last_month_payroll_histry_count;

        // Pid calculation
        $last_month_payroll_total_amount = $last_month_payroll_histry_count * $unique_pid_discount_price;

        // m2 date calculation
        // $last_month_otp_total_amount = $last_month_one_time_payment_count * $m2_discount_price;

        // sales tax per
        $sales_tax_per = isset($subscription_last->sales_tax_per) && $subscription_last->sales_tax_per > 0 ? $subscription_last->sales_tax_per : 7.25;

        // calculat total tax and other amount.
        $last_month_total = ($last_month_payroll_total_amount);

        $credit_data = Credit::whereYear('month', $formattedYear)->whereMonth('month', $formattedMonth)->orWhere('month', $subscription_end_date)->first();
        if ($credit_data) {
            $credit_amount = $credit_data->amount + $credit_data->old_balance_credit;
        }
        $last_month_taxable_amount = $last_month_total - $credit_amount;

        if ($last_month_total < $minimum_billing) {
            $last_month_taxable_amount = $minimum_billing;
            $used_credit = 0;
            $balance_credit = $credit_amount;
        } elseif ($last_month_taxable_amount < $minimum_billing) {
            $last_month_taxable_amount = $minimum_billing;
            $used_credit = $last_month_total - $last_month_taxable_amount;
            $balance_credit = $credit_amount - $used_credit;
        } else {
            $balance_credit = 0;
            $used_credit = $credit_amount;
        }

        if ($credit_data) {
            $credit_data->used_credit = $used_credit;
            $credit_data->balance_credit = $balance_credit;
            $credit_data->save();
        }

        $sales_tax_amount = (($last_month_taxable_amount * $sales_tax_per) / 100);
        $grand_last_month_total = ($last_month_taxable_amount + $sales_tax_amount);

        $billing_date = $subscription_last->end_date;

        // Creating Billing History
        $datah = [
            'subscription_id' => $subscription_last->id,
            'invoice_no' => $invoice_no,
            'amount' => round($grand_last_month_total, 2),
            'plan_id' => $plan_id,
            'plan_name' => $plan_name,
            'unique_pid_rack_price' => $unique_pid_rack_price,
            'unique_pid_discount_price' => $unique_pid_discount_price,
            'm2_rack_price' => $m2_rack_price,
            'm2_discount_price' => $m2_discount_price,
            'billing_date' => $subscription_last->end_date,
        ];
        $evereeSubscriptionBillingHistory = SubscriptionBillingHistory::Create($datah);
        $billing_history_id = $evereeSubscriptionBillingHistory->id;

        if ($evereeSubscriptionBillingHistory) {
            // Creating New subscription for next month

            $update_subscription = Subscriptions::where('id', '=', $subscription_last->id)->first();
            $endDate = Carbon::parse($update_subscription->end_date);
            if ($billing_frequency == 5) {
                $currentDate = Carbon::now();
                $newstartDate = $endDate->addDay()->startOfWeek()->toDateString();
                $newEndDate = $endDate->addDay()->endOfWeek()->toDateString();
            } else {
                $newstartDate = $endDate->copy()->addDay()->startOfMonth();
                $newEndDate = date('Y-m-t', strtotime($newstartDate));
            }

            // Creating New subscription for next month Data
            $create_subscription = [
                'plan_id' => $update_subscription->plan_id,
                'plan_type_id' => $update_subscription->plan_type_id,
                'sales_tax_per' => isset($update_subscription->sales_tax_per) && $update_subscription->sales_tax_per > 0 ? $update_subscription->sales_tax_per : 0,
                'start_date' => $newstartDate,
                'end_date' => $newEndDate,
                'minimum_billing' => $minimum_billing,
                'status' => 1,
            ];

            if (isset($update_subscription->active_user_billing) && isset($update_subscription->logged_in_active_user_billing) && isset($update_subscription->paid_active_user_billing) && isset($update_subscription->sale_approval_active_user_billing)) {
                if ($update_subscription->active_user_billing == 1) {
                    $create_subscription['active_user_billing'] = 1;
                    $create_subscription['logged_in_active_user_billing'] = 0;
                    $create_subscription['paid_active_user_billing'] = 0;
                    $create_subscription['sale_approval_active_user_billing'] = 0;
                } else {
                    $create_subscription['active_user_billing'] = 0;
                    $create_subscription['logged_in_active_user_billing'] = 1;
                    $create_subscription['paid_active_user_billing'] = 1;
                    $create_subscription['sale_approval_active_user_billing'] = 1;
                }
            }

            $update_subscription->total_pid = $last_month_payroll_histry_count;
            $update_subscription->total_m2 = $last_month_one_time_payment_count;
            $update_subscription->sales_tax_per = $sales_tax_per;
            $update_subscription->status = 0;
            $update_subscription->sales_tax_amount = round($sales_tax_amount, 2);
            $update_subscription->amount = $last_month_total;
            $update_subscription->credit_amount = $credit_amount;
            $update_subscription->used_credit = $used_credit;
            $update_subscription->balance_credit = $balance_credit;
            $update_subscription->taxable_amount = $last_month_taxable_amount;
            $update_subscription->grand_total = round($grand_last_month_total, 2);
            $update_status = $update_subscription->update();

            if ($update_status) {
                $Subscriptions = Subscriptions::Create($create_subscription);
                DB::commit();
            } else {
                DB::rollBack();
            }
        }
    }

    public function SquiAiBilling($subscription_last, $invoice_no, $billing_frequency)
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
        $subscriptStatus = true;
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
                    // dd($requestData);

                    $last_month_total = $requestData['bill_amount'] ?? 0;
                    $user_request_ids = $requestData['user_request_ids'] ?? [];

                    if ($requestData['crm_setting_status'] == 0 && $last_month_total == 0) {
                        $subscriptStatus = false;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            DB::rollBack();
        }

        if ($subscriptStatus == true) {
            $sales_tax_amount = (($last_month_total * $sales_tax_per) / 100);
            $grand_last_month_total = ($last_month_total + $sales_tax_amount);

            // genrate invoice Number
            // $invoice_no = SubscriptionBillingHistory::genrate_invoice();
            $billing_date = $subscription_last->end_date;
            $plan_id = isset($subscription_last->plans) ? $subscription_last->plans->id : null;
            $plan_name = isset($subscription_last->plans) ? $subscription_last->plans->name : null;
            $product_name = isset($subscription_last->plans) ? $subscription_last->plans->product_name : null;

            $checkEndDate = Carbon::parse($subscription_last->end_date)->format('Y-m-d');
            $checkHistory = SubscriptionBillingHistory::where('plan_id', 4)->whereDate('billing_date', $checkEndDate)->first();

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

            if ($checkHistory == null) {
                // Creating Billing History
                $evereeSubscriptionBillingHistory = SubscriptionBillingHistory::Create($datah);
                $billing_history_id = $evereeSubscriptionBillingHistory->id;
            } else {
                SubscriptionBillingHistory::where('id', $checkHistory->id)->update($datah);
                $billing_history_id = $checkHistory->id;
            }

            if ($billing_history_id) {
                // Creating New subscription for next month

                $update_subscription = Subscriptions::where('id', '=', $subscription_last->id)->first();

                $endDate = Carbon::parse($update_subscription->end_date);
                if ($billing_frequency == 5) {
                    $currentDate = Carbon::now();
                    $newstartDate = $endDate->addDay()->startOfWeek()->toDateString();
                    $newEndDate = $endDate->addDay()->endOfWeek()->toDateString();
                } else {
                    $newstartDate = $endDate->copy()->addDay()->startOfMonth();
                    $newEndDate = date('Y-m-t', strtotime($newstartDate));
                }

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
                $update_subscription->status = 0;
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
                        // Creating New subscription for next month
                        $Subscriptions = Subscriptions::Create($create_subscription);
                        // dd("hi", $user_request_ids, $subscription_last->end_date);
                    }

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
                    DB::rollBack();
                }
            }
        }
    }
}
