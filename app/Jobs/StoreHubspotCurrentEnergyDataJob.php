<?php

namespace App\Jobs;

use App\Core\Traits\HubspotTrait;
use App\Models\hubspotTransectionLog;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRowData;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreHubspotCurrentEnergyDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HubspotTrait;

    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $response_data = $this->data;
            // dd($response_data);
            $data = hubspotTransectionLog::create([
                'api_name' => 'hubspot_webhook_response',
                'response' => json_encode($response_data),
            ]);
            // $response_data = $data->response;
            // dd($response_data['objectId']);
            $dealId = (isset($response_data) && ! empty($response_data['objectId'])) ? $response_data['objectId'] : null;
            if (! empty($dealId)) {
                $url = 'https://api.hubapi.com/crm/v3/objects/deals/'.$dealId.'?archived=false&associations=contacts&properties=dealname%2Cproject_id%2Cfinancing_type%2Camount%2Csystem_size__kw_%2Cgross_epc%2Cnet_epc%2Cdealer_fee%2Chs_tcv%2Cclosedate%2Cpayment_approved_date%2Csales_representative%2Cnum_associated_contacts%2Chs_acv%2Cdealstage%2Cadders_total_amount%2Ccancel_date%2Cadders_total_amount%2Csales_rep_id_sales%2Csales_rep';
                $tokens = 'pat-na1-a2c0f38b-4fea-47d7-9cfe-6b8d6132f202';
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url, // your preferred url
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30000,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => [
                        'content-type: application/json',
                        "Authorization:Bearer $tokens",
                    ],
                ]
                );
                $response = curl_exec($ch);
                $err = curl_error($ch);
                $res = (object) json_decode($response);
                // $res =json_decode($response);
                $contactId = isset($res->associations->contacts->results[0]->id) ? $res->associations->contacts->results[0]->id : null;
                // $contactId = null;
                $getContactDetails = null;
                // dd($contactId);
                if (! empty($contactId)) {
                    $getContactDetails = $this->getContactDetailsById($contactId);

                }
                // dd($getContactDetails);
                $check = LegacyApiRowData::where('legacy_data_id', $res->id)->first();
                $checknull = LegacyApiNullData::where('legacy_data_id', $res->id)->first();
                // dd($check,$checknull);
                if (empty($check) && empty($checknull)) {
                    // Run hubspotSubroutine
                    $hubspotSubroutine = $this->hubspotSubroutineForCurrentEnergy($res, $getContactDetails);
                }

            }

            return response()->json([
                'ApiName' => 'hubspot_webhook_response',
                'status' => true,
                'message' => 'success',
            ], 200);
        } catch (\Exception $e) {
            $response_data = $request->all();
            hubspotTransectionLog::create([
                'api_name' => 'hubspot_webhook_response',
                'response' => json_encode($response_data),
            ]);
            hubspotTransectionLog::create([
                'api_name' => 'hubspot_webhook_error',
                'response' => $e->getMessage(),
            ]);

            return response()->json([
                'ApiName' => 'hubspot_webhook_response',
                'status' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 400);
        }
    }

    public function getContactDetailsById($contactId)
    {
        $contact_url = 'https://api.hubapi.com/crm/v3/objects/contacts/'.$contactId.'?properties=firstname%2Clastname%2Ccity%2Caddress%2Chs_state_code%2Czip%2Cemail%2Cmobilephone';
        $tokens = 'pat-na1-a2c0f38b-4fea-47d7-9cfe-6b8d6132f202';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $contact_url, // your preferred url
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            // CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                "Authorization:Bearer $tokens",
            ],
        ]
        );
        $contact_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get the HTTP status code
        // dd($response);
        $err = curl_error($ch);
        if ($http_code === 200) {
            $res_contact = json_decode($contact_response, true);

            return $res_contact;
        } else {
            return null;
        }
    }

    public function hubspotSubroutineForDeals($data, $getContactDetails)
    {
        // dd($data->associations->contacts->results[0]->id);
        // $userData = User::where('employee_id',$data['closer_id']['value'])->first();
        $salesRepData = null;
        $salesRepEmail = null;
        $salesRepname = null;
        $userData = User::where('employee_id', $data->associations->contacts->results[0]->id)->first();
        if (isset($data->properties->sales_rep_id_sales) && ! empty($data->properties->sales_rep_id_sales)) {
            $salesRepData = User::where('employee_id', $data->properties->sales_rep_id_sales)->first();
        }

        if (! empty($salesRepData)) {
            // $salesRepFname  = isset($salesRepData->first_name)?  $salesRepData->first_name: null;
            // $salesRepLname  = isset($salesRepData->last_name)?  $salesRepData->last_name: null;
            // $salesRepname = $salesRepFname." ".$salesRepLname;
            $salesRepEmail = isset($salesRepData->email) ? $salesRepData->email : null;
            $salesRepname = isset($salesRepData->first_name) ? $salesRepData->first_name.' '.$salesRepData->last_name : null;
        }
        // dd($getContactDetails['properties']['address']);
        // if(!empty($value->properties->full_name) && !empty($value->properties->full_address)  && !empty($value->properties->postal_code) && !empty($value->properties->contract_sign_date) && !empty($value->properties->system_size__w_) && !empty($value->properties->city) && !empty($value->properties->email)  && !empty($value->properties->state) && !empty($value->properties->dealer_fee_amount) && !empty($value->properties->phone) && !empty($value->properties->net_ppw_calc) && !empty($value->properties->dealer_fee_amount)){

        if ($salesRepData) {
            $data1['pid'] = isset($data->properties->hs_object_id) ? $data->properties->hs_object_id : null;
            $data1['aveyo_hs_id'] = isset($data->properties->hs_object_id) ? $data->properties->hs_object_id : null;
            $customer_fname = isset($getContactDetails['properties']['firstname']) ? $getContactDetails['properties']['firstname'] : '';
            $customer_lname = isset($getContactDetails['properties']['lastname']) ? $getContactDetails['properties']['lastname'] : '';
            $customer_name = $customer_fname.' '.$customer_lname;
            // $data1['install_partner']= isset($data['install_team']['value'])? $data['install_team']['value']:null;
            // $data1['homeowner_id']= isset($data['hubspot_owner_id']['value'])? $data['hubspot_owner_id']['value']:null;
            // $data1['customer_name']= isset($data['borrower_name']['value'])? $data['borrower_name']['value']:null;
            $data1['customer_name'] = isset($customer_name) ? $customer_name : null;
            $data1['customer_address'] = isset($getContactDetails['properties']['address']) ? $getContactDetails['properties']['address'] : null;
            // $data1['customer_address_2']= isset($data['address']['value'])? $data['address']['value']:null;
            $data1['customer_city'] = isset($getContactDetails['properties']['city']) ? $getContactDetails['properties']['city'] : null;
            $data1['customer_state'] = isset($getContactDetails['properties']['hs_state_code']) ? $getContactDetails['properties']['hs_state_code'] : null;
            $data1['customer_zip'] = isset($getContactDetails['properties']['zip']) ? $getContactDetails['properties']['zip'] : null;
            $data1['customer_email'] = isset($getContactDetails['properties']['email']) ? $getContactDetails['properties']['email'] : null;
            $data1['customer_phone'] = isset($getContactDetails['properties']['mobilephone']) ? $getContactDetails['properties']['mobilephone'] : null;
            // $data1['sales_rep_email']= isset($data['setter']['value'])? $data['setter']['value']:null;
            $data1['m1_date'] = null;
            $data1['m2_date'] = isset($data->properties->payment_approved_date) ? $data->properties->payment_approved_date : null;
            $data1['date_cancelled'] = isset($data->properties->cancel_date) ? $data->properties->cancel_date : null;
            $data1['kw'] = isset($data->properties->system_size__kw_) ? $data->properties->system_size__kw_ : null;
            // $data1['dealer_fee_percentage']= isset($data['dealer_fee____']['value'])? $data['dealer_fee____']['value']:null;
            $data1['dealer_fee_amount'] = isset($data->properties->dealer_fee) ? $data->properties->dealer_fee : '0';
            $data1['adders'] = isset($data->properties->adders_total_amount) ? $data->properties->adders_total_amount : null;
            // $data1['adders_description']= isset($data['adders_description']['value'])? $data['adders_description']['value']:null;
            $data1['epc'] = isset($data->properties->gross_epc) ? $data->properties->gross_epc : null;
            $data1['net_epc'] = isset($data->properties->net_epc) ? $data->properties->net_epc : null;
            // $data1['gross_account_value']= isset($data['total_cost']['value'])? $data['total_cost']['value']:null;
            $data1['gross_account_value'] = isset($data->properties->hs_tcv) ? $data->properties->hs_tcv : null;
            // $data1['product']= isset($data['project_type']['value'])? $data['project_type']['value']:null;
            // $data1['setter_id']= isset($data['setter_id']['value'])? $data['setter_id']['value']:null;
            // $data1['closer_id']= isset($data['closer_id']['value'])? $data['closer_id']['value']:null;
            $data1['closer_id'] = isset($data->properties->sales_rep_id_sales) ? $data->properties->sales_rep_id_sales : null;
            $data1['sales_rep_name'] = $salesRepname;
            $data1['sales_rep_email'] = $salesRepEmail;
            // $data1['setter_name']= isset($data['setter']['value'])? $data['setter']['value']:null;
            // $data1['contract_sign_date']= isset($data['contract_sign_date']['value'])? date('Y-m-d',strtotime($data['contract_sign_date']['value'])):null;
            $data1['email_status'] = 0;

            $checkPid = LegacyApiRowData::where('pid', $data->properties->hs_object_id)->first();

            if (! empty($checkPid)) {
                $inserted = LegacyApiRowData::where('id', $checkPid->id)->Update($data1);
                $response = hs_create_raw_data_history_api_new($data);
            } else {
                $inserted = LegacyApiRowData::create($data1);
                $response = hs_create_raw_data_history_api_new($data);
            }

        } else {
            // Insert null data in table for alert admin...............................................

            $data1['pid'] = isset($data->properties->hs_object_id) ? $data->properties->hs_object_id : null;
            $data1['aveyo_hs_id'] = isset($data->properties->hs_object_id) ? $data->properties->hs_object_id : null;
            $customer_fname = isset($getContactDetails['properties']['firstname']) ? $getContactDetails['properties']['firstname'] : '';
            $customer_lname = isset($getContactDetails['properties']['lastname']) ? $getContactDetails['properties']['lastname'] : '';
            $customer_name = $customer_fname.' '.$customer_lname;
            // $data1['install_partner']= isset($data['install_team']['value'])? $data['install_team']['value']:null;
            // $data1['aveyo_project']= isset($data['project']['value'])? $data['project']['value']:null;
            // $data1['homeowner_id']= isset($data['hubspot_owner_id']['value'])? $data['hubspot_owner_id']['value']:null;
            $data1['customer_name'] = isset($customer_name) ? $customer_name : null;
            $data1['customer_address'] = isset($getContactDetails['properties']['address']) ? $getContactDetails['properties']['address'] : null;
            // $data1['customer_address_2']= isset($data['address']['value'])? $data['address']['value']:null;
            $data1['customer_city'] = isset($getContactDetails['properties']['city']) ? $getContactDetails['properties']['city'] : null;
            $data1['customer_state'] = isset($getContactDetails['properties']['hs_state_code']) ? $getContactDetails['properties']['hs_state_code'] : null;
            $data1['customer_zip'] = isset($getContactDetails['properties']['zip']) ? $getContactDetails['properties']['zip'] : null;
            $data1['customer_email'] = isset($getContactDetails['properties']['email']) ? $getContactDetails['properties']['email'] : null;
            $data1['customer_phone'] = isset($getContactDetails['properties']['mobilephone']) ? $getContactDetails['properties']['mobilephone'] : null;
            // $data1['sales_rep_email']= isset($data['setter']['value'])? $data['setter']['value']:null;
            $data1['m1_date'] = null;
            $data1['m2_date'] = isset($data->properties->payment_approved_date) ? $data->properties->payment_approved_date : null;
            $data1['date_cancelled'] = isset($data->properties->cancel_date) ? $data->properties->cancel_date : null;
            $data1['kw'] = isset($data->properties->system_size__kw_) ? $data->properties->system_size__kw_ : null;
            // $data1['dealer_fee_percentage']= isset($data['dealer_fee____']['value'])? $data['dealer_fee____']['value']:null;
            $data1['dealer_fee_amount'] = isset($data->properties->dealer_fee) ? $data->properties->dealer_fee : null;
            $data1['adders'] = isset($data->properties->adders_total_amount) ? $data->properties->adders_total_amount : null;
            // $data1['adders_description']= isset($data['adders_description']['value'])? $data['adders_description']['value']:null;
            $data1['epc'] = isset($data->properties->gross_epc) ? $data->properties->gross_epc : null;
            $data1['net_epc'] = isset($data->properties->net_epc) ? $data->properties->net_epc : null;
            $data1['gross_account_value'] = isset($data->properties->hs_tcv) ? $data->properties->hs_tcv : null;
            // $data1['product']= isset($data['project_type']['value'])? $data['project_type']['value']:null;
            // $data1['setter_id']= isset($data['setter_id']['value'])? $data['setter_id']['value']:null;
            // $data1['closer_id']= isset($data['closer_id']['value'])? $data['closer_id']['value']:null;
            $data1['closer_id'] = isset($ddata->properties->sales_rep_id_sales) ? $ddata->properties->sales_rep_id_sales : null;
            $data1['sales_rep_name'] = $salesRepname;
            $data1['sales_rep_email'] = $salesRepEmail;
            // $data1['setter_name']= isset($data['setter']['value'])? $data['setter']['value']:null;
            // $data1['contract_sign_date']= isset($data['contract_sign_date']['value'])? date('Y-m-d',strtotime($data['contract_sign_date']['value'])):null;
            $data1['email_status'] = 0;

            // $inserted = LegacyApiNullData::Create($data);
            $getData = LegacyApiNullData::where('pid', $data->properties->hs_object_id)->first();

            if (empty($getData)) {
                $inserted = LegacyApiNullData::Create($data1);
                $response = hs_create_raw_data_history_api_new($data, $getContactDetails);
            } else {
                $inserteds = LegacyApiNullData::where('id', $getData->id)->update($data1);
                $response = hs_create_raw_data_history_api_new($data, $getContactDetails);
            }

        }
    }
}
