<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SalesInvoiceDetail extends Model
{
    use HasFactory;

    protected $table = 'sales_invoice_details';

    protected $fillable = [
        'sale_master_id',
        'customer_name',
        'customer_state',
        'pid',
        'kw',
        'customer_signoff',
        'm1_date',
        'm2_date',
        'invoice_for',    // ('unique_pid', 'm2_date')
        'billing_history_id',
        'invoice_no',
        'billing_date',
        'updated_kw',
        'updated_kw_date',
    ];

    protected $hidden = [
        'created_at',
    ];

    public static function unique_pids_m2_date_datas($permars_array = [])
    {
        $response = [];
        $subscription_end_date = $permars_array['subscription_end_date'];
        $subscription_start_date = $permars_array['subscription_start_date'];

        /** Getting pid from SalesInvoiceDetail witch invoice is done **/
        $Sales_Invoice_unique_pid_query = SalesInvoiceDetail::whereDate('customer_signoff', '<=', $subscription_end_date)->where('invoice_for', '=', 'unique_pid');
        $Sales_Invoice_unique_pid_count = $Sales_Invoice_unique_pid_query->count();

        $Sales_Invoice_m2_date_query = SalesInvoiceDetail::whereDate('customer_signoff', '<=', $subscription_end_date)->where('invoice_for', '=', 'm2_date');
        $Sales_Invoice_m2_date_count = $Sales_Invoice_m2_date_query->count();

        /** unique_pid Datas **/
        $sale_masters_unique_pid_query = SalesMaster::select('id', 'pid', 'kw', 'customer_name', 'customer_state', 'customer_signoff', 'm1_date', 'm2_date', 'created_at')->selectRaw('\'sale_masters\' as data_from')
            ->whereDate('customer_signoff', '<=', $subscription_end_date);

        $legacy_Api_Data_unique_pid_query = LegacyApiNullData::select('id', 'pid', 'kw', 'customer_name', 'customer_state', 'customer_signoff', 'm1_date', 'm2_date', 'created_at')->whereNotNull('data_source_type')->selectRaw('\'legacy_api_data_null\' as data_from')
            ->whereDate('customer_signoff', '<=', $subscription_end_date);

        if ($Sales_Invoice_unique_pid_count > 0) {
            $Sales_Invoice_unique_pid = $Sales_Invoice_unique_pid_query->pluck('pid');
            $sale_masters_unique_pid_query = $sale_masters_unique_pid_query->whereNotIn('pid', $Sales_Invoice_unique_pid);
            $legacy_Api_Data_unique_pid_query = $legacy_Api_Data_unique_pid_query->whereNotIn('pid', $Sales_Invoice_unique_pid);
        }
        if (request()->has('search') && ! empty(request()->input('search'))) {
            // dd(request()->input('search'));
            $sale_masters_unique_pid_query = $sale_masters_unique_pid_query->where(function ($q) {
                $q->orWhere('pid', 'like', '%'.request()->input('search').'%')->orWhere('customer_name', 'like', '%'.request()->input('search').'%');
            });
            $legacy_Api_Data_unique_pid_query = $legacy_Api_Data_unique_pid_query->where(function ($q) {
                $q->orWhere('pid', request()->input('search'))->orWhere('customer_name', 'like', '%'.request()->input('search').'%');
            });
        }
        $sale_masters_unique_pid = $sale_masters_unique_pid_query->get()->toArray();
        $legacy_Api_Data_unique_pid = $legacy_Api_Data_unique_pid_query->get()->toArray();
        $pid_data = array_merge($sale_masters_unique_pid, $legacy_Api_Data_unique_pid);
        $unique_pid_data = array_unique(array_column($pid_data, 'pid'));
        $final_unique_pid = [];

        foreach ($unique_pid_data as $pid_data_key => $pid_data_row) {
            $final_unique_pid[] = $pid_data[$pid_data_key];
        }
        $response['unique_pid'] = count($final_unique_pid);
        $response['unique_pid_data'] = $final_unique_pid;

        /** m2_date Datas **/
        $sale_masters_m2_date_query = SalesMaster::select('id', 'pid', 'kw', 'customer_name', 'customer_state', 'customer_signoff', 'm1_date', 'm2_date', 'created_at')->selectRaw('\'sale_masters\' as data_from')
            ->whereDate('m2_date', '<=', $subscription_end_date);

        $legacy_Api_Data_m2_date_query = LegacyApiNullData::select('id', 'pid', 'kw', 'customer_name', 'customer_state', 'customer_signoff', 'm1_date', 'm2_date', 'created_at')->whereNotNull('data_source_type')->selectRaw('\'legacy_api_data_null\' as data_from')
            ->whereDate('m2_date', '<=', $subscription_end_date);

        if ($Sales_Invoice_m2_date_count > 0) {
            $Sales_Invoice_m2_date = $Sales_Invoice_m2_date_query->pluck('pid');
            $sale_masters_m2_date_query = $sale_masters_m2_date_query->whereNotIn('pid', $Sales_Invoice_m2_date);
            $legacy_Api_Data_m2_date_query = $legacy_Api_Data_m2_date_query->whereNotIn('pid', $Sales_Invoice_m2_date);
        }

        if (request()->has('search') && ! empty(request()->input('search'))) {
            // dd(request()->input('search'));
            $sale_masters_m2_date_query = $sale_masters_m2_date_query->where(function ($q) {
                $q->orWhere('pid', 'like', '%'.request()->input('search').'%')->orWhere('customer_name', 'like', '%'.request()->input('search').'%');
            });
            $legacy_Api_Data_m2_date_query = $legacy_Api_Data_m2_date_query->where(function ($q) {
                $q->orWhere('pid', request()->input('search'))->orWhere('customer_name', 'like', '%'.request()->input('search').'%');
            });
        }
        $sale_masters_m2_date = $sale_masters_m2_date_query->get()->toArray();
        $legacy_Api_Data_m2_date = $legacy_Api_Data_m2_date_query->get()->toArray();
        $m2_date_data = array_merge($sale_masters_m2_date, $legacy_Api_Data_m2_date);
        $m2_date_pid_data = array_unique(array_column($m2_date_data, 'pid'));
        $final_m2_date_pid = [];

        foreach ($m2_date_pid_data as $m2_date_key => $m2_date_row) {
            $final_m2_date_pid[] = $m2_date_data[$m2_date_key];
        }
        $response['m2_date'] = count($final_m2_date_pid);
        $response['m2_date_data'] = $final_m2_date_pid;

        // kw updated crieteria
        $adjustedKwPidQuery = SalesInvoiceDetail::select('sales_invoice_details.pid', 'sales_invoice_details.updated_kw', 'sales_invoice_details.kw', 'sales_invoice_details.updated_kw_date', DB::raw('sales_invoice_details.updated_kw - sales_invoice_details.kw as kw_diff'), 'sale_masters.id', 'sale_masters.customer_name', 'sale_masters.customer_state', 'sale_masters.customer_signoff', 'sale_masters.m1_date', 'sale_masters.m2_date', 'sale_masters.created_at')->selectRaw('\'sale_masters\' as data_from')
            ->leftjoin('sale_masters', 'sale_masters.pid', 'sales_invoice_details.pid')
            ->whereNotNull('sales_invoice_details.updated_kw')
            ->whereBetween('sales_invoice_details.updated_kw_date', [$subscription_start_date, $subscription_end_date])
            ->where('sales_invoice_details.invoice_for', '=', 'm2_date');

        $adjustedKwPidCount = $adjustedKwPidQuery->count();

        if ($adjustedKwPidCount > 0) {
            $response['kw_adjusted_all_pids'] = $adjustedKwPidQuery->pluck('pid')->toArray();
            $adjustedKwPidData = $adjustedKwPidQuery->get()->toArray();
            $response['kw_adjusted_data'] = $adjustedKwPidData;
        }

        return $response;
    }

    public static function unique_pids_m2_date_datas_pids_wise($permars_array = [])
    {
        // print_r($permars_array);die();
        $response = [];
        // $subscription_end_date = $permars_array['subscription_end_date'];

        /** Getting pid from SalesInvoiceDetail witch invoice is done **/
        $Sales_Invoice_unique_pid_query = SalesInvoiceDetail::whereIn('pid', $permars_array)->where('invoice_for', '=', 'unique_pid');
        $Sales_Invoice_unique_pid_count = $Sales_Invoice_unique_pid_query->count();

        $Sales_Invoice_m2_date_query = SalesInvoiceDetail::whereIn('pid', $permars_array)->where('invoice_for', '=', 'm2_date');
        $Sales_Invoice_m2_date_count = $Sales_Invoice_m2_date_query->count();

        /** unique_pid Datas **/
        $sale_masters_unique_pid_query = SalesMaster::select('id', 'pid', 'kw', 'customer_name', 'customer_state', 'customer_signoff', 'm1_date', 'm2_date', 'created_at')->selectRaw('\'sale_masters\' as data_from')
            ->whereIn('pid', $permars_array);

        $legacy_Api_Data_unique_pid_query = LegacyApiNullData::select('id', 'pid', 'kw', 'customer_name', 'customer_state', 'customer_signoff', 'm1_date', 'm2_date', 'created_at')->whereNotNull('data_source_type')->selectRaw('\'legacy_api_data_null\' as data_from')
            ->whereIn('pid', $permars_array);

        /*if($Sales_Invoice_unique_pid_count > 0){
            $Sales_Invoice_unique_pid = $Sales_Invoice_unique_pid_query->pluck('pid');
            $sale_masters_unique_pid_query = $sale_masters_unique_pid_query->whereNotIn('pid', $Sales_Invoice_unique_pid);
            $legacy_Api_Data_unique_pid_query = $legacy_Api_Data_unique_pid_query->whereNotIn('pid', $Sales_Invoice_unique_pid);
        }  */
        if (request()->has('search') && ! empty(request()->input('search'))) {
            // dd(request()->input('search'));
            $sale_masters_unique_pid_query = $sale_masters_unique_pid_query->where(function ($q) {
                $q->orWhere('pid', 'like', '%'.request()->input('search').'%')->orWhere('customer_name', 'like', '%'.request()->input('search').'%');
            });
            $legacy_Api_Data_unique_pid_query = $legacy_Api_Data_unique_pid_query->where(function ($q) {
                $q->orWhere('pid', request()->input('search'))->orWhere('customer_name', 'like', '%'.request()->input('search').'%');
            });
        }
        $sale_masters_unique_pid = $sale_masters_unique_pid_query->get()->toArray();
        $legacy_Api_Data_unique_pid = $legacy_Api_Data_unique_pid_query->get()->toArray();
        $pid_data = array_merge($sale_masters_unique_pid, $legacy_Api_Data_unique_pid);
        $unique_pid_data = array_unique(array_column($pid_data, 'pid'));
        $final_unique_pid = [];

        foreach ($unique_pid_data as $pid_data_key => $pid_data_row) {
            $final_unique_pid[] = $pid_data[$pid_data_key];
        }
        $response['unique_pid'] = count($final_unique_pid);
        $response['unique_pid_data'] = $final_unique_pid;

        /** m2_date Datas **/
        $sale_masters_m2_date_query = SalesMaster::select('id', 'pid', 'kw', 'customer_name', 'customer_state', 'customer_signoff', 'm1_date', 'm2_date', 'created_at')->selectRaw('\'sale_masters\' as data_from')
            ->whereIn('pid', $permars_array);

        $legacy_Api_Data_m2_date_query = LegacyApiNullData::select('id', 'pid', 'kw', 'customer_name', 'customer_state', 'customer_signoff', 'm1_date', 'm2_date', 'created_at')->whereNotNull('data_source_type')->selectRaw('\'legacy_api_data_null\' as data_from')
            ->whereIn('pid', $permars_array);

        if ($Sales_Invoice_m2_date_count > 0) {
            $Sales_Invoice_m2_date = $Sales_Invoice_m2_date_query->pluck('pid');
            $sale_masters_m2_date_query = $sale_masters_m2_date_query->whereNotIn('pid', $Sales_Invoice_m2_date);
            $legacy_Api_Data_m2_date_query = $legacy_Api_Data_m2_date_query->whereNotIn('pid', $Sales_Invoice_m2_date);
        }

        if (request()->has('search') && ! empty(request()->input('search'))) {
            // dd(request()->input('search'));
            $sale_masters_m2_date_query = $sale_masters_m2_date_query->where(function ($q) {
                $q->orWhere('pid', 'like', '%'.request()->input('search').'%')->orWhere('customer_name', 'like', '%'.request()->input('search').'%');
            });
            $legacy_Api_Data_m2_date_query = $legacy_Api_Data_m2_date_query->where(function ($q) {
                $q->orWhere('pid', request()->input('search'))->orWhere('customer_name', 'like', '%'.request()->input('search').'%');
            });
        }
        $sale_masters_m2_date = $sale_masters_m2_date_query->get()->toArray();
        $legacy_Api_Data_m2_date = $legacy_Api_Data_m2_date_query->get()->toArray();
        $m2_date_data = array_merge($sale_masters_m2_date, $legacy_Api_Data_m2_date);
        $m2_date_pid_data = array_unique(array_column($m2_date_data, 'pid'));
        $final_m2_date_pid = [];

        foreach ($m2_date_pid_data as $m2_date_key => $m2_date_row) {
            $final_m2_date_pid[] = $m2_date_data[$m2_date_key];
        }
        $response['m2_date'] = count($final_m2_date_pid);
        $response['m2_date_data'] = $final_m2_date_pid;

        return $response;
    }

    public static function everee_billing($permars_array = [])
    {
        $response = [];
        $subscription_end_date = $permars_array['subscription_end_date'];
        $subscription_start_date = $permars_array['subscription_start_date'];

        $payroll_histry_id_query = PayrollHistory::with(['usersdata' => function ($query) {
            $query->withoutGlobalScopes()->select('id', 'first_name', 'last_name', 'email');
        }])
            ->select('id', 'user_id', 'everee_external_id', 'everee_payment_requestId', 'everee_paymentId', 'net_pay', 'pay_period_from', 'pay_period_to', 'everee_webhook_json', 'created_at')
            ->whereDate('created_at', '<=', $subscription_end_date)->whereDate('created_at', '>=', $subscription_start_date)->where('pay_type', 'Bank');

        if (request()->has('search') && ! empty(request()->input('search'))) {

            $payroll_histry_id_query->whereHas('usersdata', function ($query) {
                return $query->where(DB::raw("concat(first_name, ' ', last_name)"), 'like', '%'.request()->input('search').'%');
            });

        }
        $final_payroll_hiistry_id = [];
        $final_payroll_hiistry_id = $payroll_histry_id_query->get()->toArray();

        $response['payroll_histry_id'] = count($final_payroll_hiistry_id);
        $response['payroll_histry_id_data'] = $final_payroll_hiistry_id;

        $one_time_date_query = OneTimePayments::with(['usersdata' => function ($query) {
            $query->withoutGlobalScopes()->select('id', 'first_name', 'last_name', 'email');
        }])->select('id', 'user_id', 'everee_external_id', 'everee_payment_req_id as everee_payment_requestId', 'everee_paymentId', 'amount as net_pay', 'pay_date as pay_period_from', 'pay_date as pay_period_to', 'everee_webhook_response as everee_webhook_json', 'created_at')->whereDate('created_at', '<=', $subscription_end_date)->whereDate('created_at', '>=', $subscription_start_date);

        if (request()->has('search') && ! empty(request()->input('search'))) {
            $payroll_histry_id_query->whereHas('usersdata', function ($query) {
                return $query->where(DB::raw("concat(first_name, ' ', last_name)"), 'like', '%'.request()->input('search').'%');
            });
        }

        $final_one_time_date_id = [];
        $final_one_time_date_id = $one_time_date_query->get()->toArray();

        $response['one_time_payment_date'] = count($final_one_time_date_id);
        $response['one_time_payment_date_data'] = $final_one_time_date_id;

        return $response;
    }
}
