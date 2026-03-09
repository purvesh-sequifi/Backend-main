<?php

namespace App\Models;

// use AWS\CRT\Log;
use App\Core\Traits\EditSaleTrait;
use App\Core\Traits\SetterSubroutineListTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Buckets extends Model
{
    use EditSaleTrait;
    use HasFactory;
    use SetterSubroutineListTrait;

    protected $table = 'buckets';

    // public $search;

    protected $fillable = [
        'id',
        'bucket_type',
        'name',
        'display_order',
        'hide_status',
        'colour_code',
        'warning_day',
        'danger_day',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function bucketsubtasks(): HasMany
    {
        return $this->hasMany(\App\Models\BucketSubTask::class, 'bucket_id', 'id');
    }

    public function jobucketbybucket(): HasMany
    {
        return $this->hasMany(\App\Models\Buckets::class, 'id', 'bucket_id');
    }

    public static function movejobprocessbypid($pid, $bucket_id)
    {
        $job_id = Crmsaleinfo::select('id')->where('pid', $pid)->first();
        self::movejobprocess($job_id->id, $bucket_id);
    }

    public static function movejobprocess($job_id, $bucket_id)
    {
        $updatedata['active'] = 0;
        Bucketbyjob::where('job_id', $job_id)->update($updatedata);
        $getdata = Bucketbyjob::where('bucket_id', $bucket_id)->where('job_id', $job_id)->get();
        // print_r($getdata);die();
        if ($getdata->isEmpty()) {
            $data = [
                'bucket_id' => $bucket_id,
                'job_id' => $job_id,
                'active' => 1,
            ];
            Bucketbyjob::create($data);
        } else {
            $updatedata['active'] = 1;
            Bucketbyjob::where('bucket_id', $bucket_id)->where('job_id', $job_id)->update($updatedata);
        }
        $getsubtasks = BucketSubTask::where('bucket_id', $bucket_id)->get();
        foreach ($getsubtasks as $getsubtask) {
            $pdata = [
                'bucket_sutask_id' => $getsubtask->id,
                'job_id' => $job_id,
            ];
            self::checksubtaskaddandupdate($pdata);
        }
    }

    public static function checksubtaskaddandupdate($data)
    {
        $getdata_buckets_subtask = BucketSubTaskByJob::where('bucket_sutask_id', $data['bucket_sutask_id'])->where('job_id', $data['job_id'])->get();
        if ($getdata_buckets_subtask->isEmpty()) {
            BucketSubTaskByJob::create($data);
        }
    }

    public static function addcrmsale($pid, $bucket_id)
    {
        $bucket_id = $bucket_id ?? 0;
        $CrmsaleinfoData = Crmsaleinfo::where('pid', $pid)->first();
        if (empty($CrmsaleinfoData)) {
            $data = [
                'pid' => $pid,
                'created_id' => Auth()->user()->id,
            ];
            $insertData = Crmsaleinfo::create($data);
            $job_id = $insertData->id;
            $pdata = [
                'job_id' => $job_id,
                'bucket_id' => $bucket_id,
                'active' => 1,
            ];
            $insertData = Bucketbyjob::create($pdata);
            $getsubtasks = BucketSubTask::where('bucket_id', $bucket_id)->get();
            foreach ($getsubtasks as $getsubtask) {
                $pdata = [
                    'bucket_sutask_id' => $getsubtask->id,
                    'job_id' => $job_id,
                ];
                self::checksubtaskaddandupdate($pdata);
            }
        } else {

        }
        self::addupdatesubsction();
    }

    public static function addupdatesubsction($id = 0)
    {
        if ($id == 0) {
            $planName = 'SequiCRM';
            $planData = Plans::where('product_name', $planName)->first();
        } else {
            $planData = Plans::where('id', $id)->first();
        }

        if ($planData != null) {
            $planId = $planData->id;
            $perjobamount = $planData->unique_pid_rack_price;
            $profiledata = CompanyProfile::where('id', 1)->first();
            $billing_frequency = (isset($profiledata->frequency_type_id) && ! empty($profiledata->frequency_type_id)) ? $profiledata->frequency_type_id : 1;
            $currentDate = Carbon::now();
            if ($billing_frequency == 5) {
                $startDate = $currentDate->startOfWeek()->toDateString();
                $endDate = $currentDate->endOfWeek()->toDateString();
                $checkSCSubscription = Subscriptions::where('status', 1)->where('plan_id', $planData->id)->where('status', 1)->first();
            } else {
                $startDate = $currentDate->startOfMonth()->toDateString();
                $endDate = $currentDate->endOfMonth()->toDateString();
                $checkSCSubscription = Subscriptions::where('status', 1)->where('plan_id', $planData->id)->where('start_date', $startDate)->where('end_date', $endDate)->first();
            }

            $sales_tax_per = $checkSCSubscription ? $checkSCSubscription->sales_tax_per : 7.25;
            // Get Total JOB
            $currentMonth = Carbon::now()->month;
            $currentYear = Carbon::now()->year;
            $totaljob = Crmsaleinfo::whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->count();
            $amount = ($perjobamount * $totaljob);
            $sales_tax_amount = (($amount * $sales_tax_per) / 100);
            $grand_total = ($amount + $sales_tax_amount);
            if ($checkSCSubscription) {
                $update['total_pid'] = $totaljob;
                $update['sales_tax_amount'] = $sales_tax_amount;
                $update['amount'] = $amount;
                $update['grand_total'] = $grand_total;

                $checkSCSubscription->update($update);
            } else {
                $newSubsscription = new Subscriptions;
                $newSubsscription->plan_type_id = 1;
                $newSubsscription->plan_id = $planId;
                $newSubsscription->start_date = $startDate;
                $newSubsscription->end_date = $endDate;
                $newSubsscription->status = 1;
                $newSubsscription->total_pid = $totaljob;
                $newSubsscription->sales_tax_per = $sales_tax_per;
                $newSubsscription->sales_tax_amount = $sales_tax_amount;
                $newSubsscription->amount = $amount;
                $newSubsscription->grand_total = $grand_total;

                $newSubsscription->save();
            }
        }
    }

    public static function SequiCRMBilling($previous, $invoice_no, $billing_frequency = '')
    {
        DB::beginTransaction();
        $sequicrm = Crms::where('id', 7)->first();
        $m2_discount_price = 0;
        $balance_credit = 0;
        $used_credit = 0;
        $permars_array = [];
        $subscription_end_date = $previous->end_date;
        $subscription_start_date = $previous->start_date;
        $minimum_billing = isset($previous->minimum_billing) ? $previous->minimum_billing : 0;
        $unique_pid_rack_price = isset($previous->plans) ? $previous->plans->unique_pid_rack_price : 0;
        $credit_amount = 0;

        // sales tax per
        $sales_tax_per = isset($previous->sales_tax_per) && $previous->sales_tax_per > 0 ? $previous->sales_tax_per : 7.25;

        // Get total jobs for the current month and year
        $totaljob = Crmsaleinfo::whereBetween('created_at', [$subscription_start_date, $subscription_end_date])
            ->count();

        // calculat total tax and other amount.
        $last_month_total = $unique_pid_rack_price * $totaljob;

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
        $billing_date = $previous->end_date;
        $plan_id = isset($previous->plans) ? $previous->plans->id : null;
        $plan_name = isset($previous->plans) ? $previous->plans->name : null;
        $product_name = isset($previous->plans) ? $previous->plans->product_name : null;
        $unique_pid_discount_price = isset($previous->plans) ? $previous->plans->unique_pid_discount_price : 0;

        // Creating Billing History
        $datah = [
            'subscription_id' => $previous->id,
            'invoice_no' => $invoice_no,
            'amount' => round($grand_last_month_total, 2),
            'plan_id' => $plan_id,
            'plan_name' => $plan_name,
            'unique_pid_rack_price' => $unique_pid_rack_price,
            'unique_pid_discount_price' => $unique_pid_discount_price,
            'm2_rack_price' => 0,
            'm2_discount_price' => 0,
            'billing_date' => $previous->end_date,
        ];
        $currentDate = Carbon::now();
        $endday = $currentDate->endOfMonth()->toDateString();
        $subscriptionBillingHistory = [];
        if ($previous->end_date != $endday) {
            $subscriptionBillingHistory = SubscriptionBillingHistory::Create($datah);
            $billing_history_id = $subscriptionBillingHistory->id;
        }

        if (! empty($subscriptionBillingHistory)) {
            // Creating New subscription for next month
            $update_subscription = Subscriptions::where('id', '=', $previous->id)->first();
            $endDate = Carbon::parse($update_subscription->end_date);
            if ($billing_frequency == 5) {
                $currentDate = Carbon::now();
                $newstartDate = $currentDate->startOfWeek()->toDateString();
                $newEndDate = $currentDate->endOfWeek()->toDateString();
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
            $update_subscription->total_pid = $totaljob;
            $update_status = $update_subscription->update();

            if ($update_status) {

                if (isset($sequicrm->status) && ($sequicrm->status == 1)) {
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

    public static function getpidstotals($request, $id)
    {
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $unique_pid_rack_price = $unique_pid_discount_price = $m2_rack_price = $m2_discount_price = $pid_count = 0;
        $total_price = $sales_tax_amount = $sales_tax_per = $grand_total = $pid_kw_sum = 0;
        $pricebilled = [];
        $getdata = [];
        $status_code = 400;
        $status = false;
        $message = 'subscription not found!';
        $subscription = Subscriptions::with('plans', 'billingType')->where('id', $id)->first();
        if (! empty($subscription) && $subscription != null) {
            $status_code = 200;
            $status = true;
            $message = 'Data get!';
            $start_date = $subscription->start_date;
            $end_date = $subscription->end_date;
            $alljobs = Crmsaleinfo::select('pid')->whereBetween('created_at', [$start_date, $end_date])
                ->get();
            $totalpid = $alljobs->count();
            $Sales_Invoice_unique_pid = $alljobs->pluck('pid');

            $unique_pids_m2_date_datas = SalesInvoiceDetail::unique_pids_m2_date_datas_pids_wise($Sales_Invoice_unique_pid);
            // print_r($unique_pids_m2_date_datas);die();
            $unique_pid_data = $unique_pids_m2_date_datas['unique_pid_data'];
            $m2_date_data = $unique_pids_m2_date_datas['m2_date_data'];

            $pid_count = count($unique_pid_data);
            $pid_kw_sum = round(array_sum(array_column($unique_pid_data, 'kw')), 2);
            $pid_count = count($unique_pid_data);

            // calculation
            $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
            $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;
            $m2_rack_price = isset($subscription->plans) ? $subscription->plans->m2_rack_price : 0;
            $m2_discount_price = isset($subscription->plans) ? $subscription->plans->m2_discount_price : 0;
            $pid_total_amount = ($unique_pid_rack_price * $pid_count);
            $pid_total_amount = ($pid_total_amount - ($pid_count * $unique_pid_discount_price));
            // $pid_total_amount = $pid_kw_sum * 1000 * $unique_pid_discount_price;
            $total_price = $pid_total_amount;
            // echo $total_price;die();
            $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
            $sales_tax_amount = (($total_price * $sales_tax_per) / 100);
            $grand_total = ($total_price + $sales_tax_amount);

            foreach ($unique_pid_data as $data) {
                $billed_price = $unique_pid_rack_price;
                $price = $unique_pid_rack_price - $unique_pid_discount_price;
                $getdata[] = [
                    'id' => $data['id'],
                    'pid' => $data['pid'],
                    'customer_name' => $data['customer_name'],
                    'customer_state' => $data['customer_state'],
                    'data_from' => $data['data_from'],
                    'kw' => $data['kw'],
                    'm2_date' => $data['m2_date'],
                    'approval_date' => $data['customer_signoff'],
                    'price' => $price,
                    'billed_price' => round($billed_price, 2),
                ];
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

    public static function addplanandsubscription($crm_id)
    {
        $setting_info = CrmSetting::where('crm_id', $crm_id)->first();
        if ($setting_info->status) {
            $planName = 'SequiCRM';
            $planData = Plans::where('product_name', $planName)->first();
            if ($planData) {
                $update['name'] = $setting_info->plan_name;
                $update['unique_pid_rack_price'] = $setting_info->amount_per_job;
                $planData->update($update);
            } else {
                $planData = new Plans;
                $planData->id = 6;
                $planData->name = $setting_info->plan_name;
                $planData->product_name = $planName;
                $planData->unique_pid_rack_price = $setting_info->amount_per_job;
                $planData->unique_pid_discount_price = 0;
                $planData->m2_rack_price = 0;
                $planData->m2_discount_price = 0;
                $planData->created_at = new \DateTime;
                $planData->updated_at = new \DateTime;
                $planData->save();
            }
            // Add Subscription
            self::addupdatesubsction($planData->id);
        }

    }
}
