<?php

namespace App\Http\Controllers\API\Plaid;

use App\Http\Controllers\Controller;
use App\Models\LegacyApiRowData;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PlaidController extends Controller
{
    /**
     * Using Http
     *
     * @return response()
     */
    public function index(Request $request)
    {
        // return $request;
        $data = [
            'client_id' => $request->client_id,
            'secret' => $request->secret,
            'client_name' => $request->client_name,
            'user' => [
                'client_user_id' => 'unique_user_id',
            ],
            'products' => ['auth'],
            'country_codes' => ['US'],
            'language' => 'en',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://sandbox.plaid.com/link/token/create');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['content-type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode((object) $data, JSON_HEX_APOS | JSON_HEX_QUOT));
        $response = curl_exec($ch);
    }

    /**
     * Using GuzzleHttp
     *
     * @return response()
     */
    public function publicToken(Request $request)
    {
        $data = [
            'client_id' => $request->client_id,
            'secret' => $request->secret,
            'institution_id' => $request->institution_id,
            'initial_products' => $request->initial_products,
            'options' => [
                'webhook' => 'https://www.genericwebhookurl.com/webhook',
            ],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://sandbox.plaid.com/sandbox/public_token/create');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['content-type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode((object) $data, JSON_HEX_APOS | JSON_HEX_QUOT));
        $response = curl_exec($ch);
    }

    public function ExchangePlaid(Request $request)
    {
        $data = [
            'client_id' => $request->client_id,
            'secret' => $request->secret,
            'public_token' => $request->public_token,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://sandbox.plaid.com/item/public_token/exchange');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['content-type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode((object) $data, JSON_HEX_APOS | JSON_HEX_QUOT));
        $response = curl_exec($ch);
    }

    public function RetrieveAuth(Request $request)
    {
        $data = [
            'client_id' => $request->client_id,
            'secret' => $request->secret,
            'access_token' => $request->access_token,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://sandbox.plaid.com/auth/get');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['content-type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode((object) $data, JSON_HEX_APOS | JSON_HEX_QUOT));
        $response = curl_exec($ch);
    }

    public function salesdataprocess($id)
    {
        // return
        $data = SaleMasterProcess::with(
            'setter1Detail',
            'setter2Detail',
            'closer1Detail',
            'closer2Detail',
            'status',
            'status1',
            'closer1m1paidstatus',
            'closer2m1paidstatus',
            'setter1m1paidstatus',
            'setter2m1paidstatus',
            'closer1m2paidstatus',
            'closer2m2paidstatus',
            'setter1m2paidstatus',
            'Setter2m2paidstatus'
        )
            ->where('pid', $id)->first();
        //    dd($data);
        $data1 =
            [
                // dd($data->Override),
                'id' => $data->id,
                'pid' => $data->pid,
                'setter1Detail' => isset($data->setter1Detail->first_name, $data->setter1Detail->last_name) ? $data->setter1Detail->first_name.' '.$data->setter1Detail->last_name : '',
                'setter2Detail' => isset($data->setter2Detail->first_name, $data->setter2Detail->last_name) ? $data->setter2Detail->first_name.' '.$data->setter2Detail->last_name : '',
                'closer1Detail' => isset($data->closer1Detail->first_name, $data->closer1Detail->last_name) ? $data->closer1Detail->first_name.' '.$data->closer1Detail->last_name : '',
                'closer2Detail' => isset($data->closer2Detail->first_name, $data->closer2Detail->last_name) ? $data->closer2Detail->first_name.' '.$data->closer2Detail->last_name : '',
                'closer1_m1' => isset($data->closer1_m1) ? $data->closer1_m1 : '',
                'closer2_m1' => isset($data->closer2_m1) ? $data->closer2_m1 : '',
                'setter1_m1' => isset($data->setter1_m1) ? $data->setter1_m1 : '',
                'setter2_m1' => isset($data->setter2_m1) ? $data->setter2_m1 : '',
                'closer1_m2' => isset($data->closer1_m2) ? $data->closer1_m2 : '',
                'closer2_m2' => isset($data->closer2_m2) ? $data->closer2_m2 : '',
                'setter1_m2' => isset($data->setter1_m2) ? $data->setter1_m2 : '',
                'setter2_m2' => isset($data->setter2_m2) ? $data->setter2_m2 : '',
                'closer1_commission' => isset($data->closer1_commission) ? $data->closer1_commission : '',
                'closer2_commission' => isset($data->closer2_commission) ? $data->closer2_commission : '',
                'setter1_commission' => isset($data->setter1_commission) ? $data->setter1_commission : '',
                'setter2_commission' => isset($data->setter2_commission) ? $data->setter2_commission : '',
                'closer1_m1_paid_date' => isset($data->closer1_m1_paid_date) ? $data->closer1_m1_paid_date : '',
                'closer2_m1_paid_date' => isset($data->closer2_m1_paid_date) ? $data->closer2_m1_paid_date : '',
                'setter1_m1_paid_date' => isset($data->setter1_m1_paid_date) ? $data->setter1_m1_paid_date : '',
                'setter2_m1_paid_date' => isset($data->setter2_m1_paid_date) ? $data->setter2_m1_paid_date : '',
                'closer1_m2_paid_date' => isset($data->closer1_m2_paid_date) ? $data->closer1_m2_paid_date : '',
                'closer2_m2_paid_date' => isset($data->closer2_m2_paid_date) ? $data->closer2_m2_paid_date : '',
                'setter1_m2_paid_date' => isset($data->setter1_m2_paid_date) ? $data->setter1_m2_paid_date : '',
                'setter2_m2_paid_date' => isset($data->setter2_m2_paid_date) ? $data->setter2_m2_paid_date : '',
                'closer1_m1_paid_status' => isset($data->closer1m1paidstatus->account_status) ? $data->closer1m1paidstatus->account_status : '',
                'closer2_m1_paid_status' => isset($data->closer2m1paidstatus->account_status) ? $data->closer2m1paidstatus->account_status : '',
                'setter1_m1_paid_status' => isset($data->Setter1m1paidstatus->account_status) ? $data->Setter1m1paidstatus->account_status : '',
                'setter2_m1_paid_status' => isset($data->Setter2m1paidstatus->account_status) ? $data->Setter2m1paidstatus->account_status : '',
                'closer1_m2_paid_status' => isset($data->closer1m2paidstatus->account_status) ? $data->closer1m2paidstatus->account_status : '',
                'closer2_m2_paid_status' => isset($data->closer2m2paidstatus->account_status) ? $data->closer2m2paidstatus->account_status : '',
                'setter1_m2_paid_status' => isset($data->Setter1m2paidstatus->account_status) ? $data->Setter1m2paidstatus->account_status : '',
                'setter2_m2_paid_status' => isset($data->Setter2m2paidstatus->account_status) ? $data->Setter2m2paidstatus->account_status : '',
                'mark_account_status' => isset($data->status->account_status) ? $data->status->account_status : '',
                'pid_status' => isset($data->status1->account_status) ? $data->status1->account_status : '',
            ];
        if ($data1) {
            // dd($data10['data1']);
            return response()->json([
                'ApiName' => 'sales-data-process-list-by-id',
                'status' => true,
                'message' => 'Successfully',
                'data' => $data1,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'sales-data-process-list-by-id',
                'status' => false,
                'message' => '',
                // 'data'    => $data,
            ], 200);
        }
    }

    public function salesdata($id)
    {
        // return $id;
        $data = SalesMaster::where('pid', $id)->first();
        if ($data) {
            return response()->json([
                'ApiName' => 'sales-data-process-list-by-id',
                'status' => true,
                'message' => 'Successfully',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'sales-data-process-list-by-id',
                'status' => false,
                'message' => '',
                // 'data'    => $data,
            ], 200);
        }
    }

    public function salerawdata($id)
    {
        // return $id;
        $data = LegacyApiRowData::with('setter')->where('pid', $id)->get();
        $data->transform(function ($data) {
            return [
                // dd($data->Override),
                'id' => $data->id,
                'pid' => isset($data->pid) ? $data->pid : '',
                'm1_date' => isset($data->m1_date) ? $data->m1_date : '',
                'm2_date' => isset($data->m2_date) ? $data->m2_date : '',
            ];
        });
        // dd()
        // foreach
        if ($data) {
            return response()->json([
                'ApiName' => 'sales-data-process-list-by-id',
                'status' => true,
                'message' => 'Successfully',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'sales-data-process-list-by-id',
                'status' => false,
                'message' => '',
                // 'data'    => $data,
            ], 200);
        }
    }

    public function salerawdata1($id, $pid)
    {
        // return $id;
        $data = LegacyApiRowData::with('setter')->where('pid', $pid)->where('id', $id)->first();
        $data1 =
            [
                'id' => $data->id,
                'pid' => $data->pid,
                'homeowner_id' => $data->homeowner_id,
                'legacy_data_id' => $data->legacy_data_id,
                'proposal_id' => $data->proposal_id,
                'customer_name' => $data->customer_name,
                'customer_address' => $data->customer_address,
                'customer_address_2' => $data->customer_address_2,
                'customer_city' => $data->customer_city,
                'customer_state' => $data->customer_state,
                'customer_zip' => $data->customer_zip,
                'customer_email' => $data->customer_email,
                'customer_phone' => $data->customer_phone,
                'setter_id' => isset($data->setter->first_name, $data->setter->last_name) ? $data->setter->first_name.' '.$data->setter->last_name : '',
                'employee_id' => $data->employee_id,
                'sales_rep_name' => $data->sales_rep_name,
                'sales_rep_email' => $data->sales_rep_email,
                'install_partner' => $data->install_partner,
                'customer_signoff' => $data->customer_signoff,
                'm1_date' => $data->m1_date,
                'scheduled_install' => $data->scheduled_install,
                'install_complete_date' => $data->install_complete_date,
                'm2_date' => $data->m2_date,
                'date_cancelled' => $data->date_cancelled,
                'return_sales_date' => $data->return_sales_date,
                'gross_account_value' => $data->gross_account_value,
                'cash_amount' => $data->cash_amount,
                'loan_amount' => $data->loan_amount,
                'kw' => $data->kw,
                'dealer_fee_percentage' => $data->dealer_fee_percentage,
                'adders' => $data->adders,
                'adders_description' => $data->adders_description,
                'funding_source' => $data->funding_source,
                'financing_rate' => $data->financing_rate,
                'financing_term' => $data->financing_term,
                'product' => $data->product,

            ];
        if ($data1) {
            return response()->json([
                'ApiName' => 'sales-data-process-list-by-id',
                'status' => true,
                'message' => 'Successfully',
                'data' => $data1,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'sales-data-process-list-by-id',
                'status' => false,
                'message' => '',
                // 'data'    => $data,
            ], 200);
        }
    }
}
