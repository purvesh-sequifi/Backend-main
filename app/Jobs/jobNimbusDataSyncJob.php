<?php

namespace App\Jobs;

use App\Core\Traits\JobNimbusTrait;
use App\Models\Crms;
use App\Models\LegacyWeeklySheet;
use App\Models\User;
use Illuminate\Bus\Queueable;
// custom  use pattern
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

// end custom use pattern

class jobNimbusDataSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, JobNimbusTrait, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // my logic start from here
        try {
            $new_pids = $updated_pids = $api_data = [];
            $total_records = 0;

            $crms = Crms::whereHas('crmSetting')->with('crmSetting')->where(['id' => 4, 'status' => 1])->first();
            if (empty($crms)) {
                exit('Your JobNimbus setting is not active');
            }
            $jobNimbusCrmSetting = json_decode($crms->crmSetting->value);
            $api_key = $jobNimbusCrmSetting->api_key; //  bearer  token
            $method = 'GET';
            $base_url = 'https://app.jobnimbus.com/api1/jobs';
            $params = [
                'size' => 1,
                'from' => 0,
                'fields' => 'jnid',
                'filter' => '{"must":[{"term":{"record_type":27,"record_type_name":"Solar | RES"}}]}',

            ];
            $url = $base_url.'?'.http_build_query($params);
            $api_response = $this->makeCurlRequest($api_key, $method, $url);
            $data = json_decode($api_response);
            $total_record = $data->count;
            $perPageRecord = 100;
            $total_page = ceil($total_record / $perPageRecord);
            for ($i = 0; $i < $total_page; $i++) {
                $params = [
                    'size' => $perPageRecord,
                    'from' => $i * $perPageRecord,
                    'filter' => '{"must":[{"term":{"record_type":27,"record_type_name":"Solar | RES"}}]}',
                ];
                $url = $base_url.'?'.http_build_query($params);
                $api_response = $this->makeCurlRequest($api_key, $method, $url);
                $data = json_decode($api_response, true);

                if (isset($data['results'])) {

                    foreach ($data['results'] as $filteredResult) {
                        if (isset($filteredResult['record_type_name']) && $filteredResult['record_type_name'] === 'Solar | RES') {
                            $statusName = isset($filteredResult['status_name']) ? $filteredResult['status_name'] : null;
                            $dateStatusChange = isset($filteredResult['date_status_change']) ? date('Y-m-d', $filteredResult['date_status_change']) : null;
                            $dataCreate = [
                                'pid' => isset($filteredResult['number']) ? $filteredResult['number'] : null,
                                'customer_name' => isset($filteredResult['name']) ? $filteredResult['name'] : null,
                                'customer_phone' => isset($filteredResult['parent_home_phone']) ? $filteredResult['parent_home_phone'] : null,
                                'customer_address' => isset($filteredResult['address_line1']) ? $filteredResult['address_line1'] : null,
                                'customer_address_2' => isset($filteredResult['address_line2']) ? $filteredResult['address_line2'] : null,
                                'customer_state' => isset($filteredResult['state_text']) ? $filteredResult['state_text'] : null,
                                'customer_city' => isset($filteredResult['city']) ? $filteredResult['city'] : null,
                                'customer_zip' => isset($filteredResult['zip']) ? $filteredResult['zip'] : null,
                                'product' => isset($filteredResult['(S) Loan/Term/Rate']) ? $filteredResult['(S) Loan/Term/Rate'] : null,
                                'kw' => isset($filteredResult['(S) System Size (KW)']) ? $filteredResult['(S) System Size (KW)'] : null,
                                'sales_rep_name' => isset($filteredResult['sales_rep_name']) ? $filteredResult['sales_rep_name'] : null,
                                'gross_account_value' => isset($filteredResult['(S) Contract Amount']) ? $filteredResult['(S) Contract Amount'] : null,
                                // 'customer_signoff' => isset($filteredResult['date_created'])?date('Y-m-d',$filteredResult['date_created']):null,
                                'install_partner' => isset($filteredResult['(S) EPC ']) ? $filteredResult['(S) EPC '] : null,
                                'net_epc' => isset($filteredResult['(S) PPW Sold']) ? $filteredResult['(S) PPW Sold'] : null,
                                'pid_status' => isset($filteredResult['status_name']) ? $filteredResult['status_name'] : null,
                                'data_source_type' => 'api-jobnimbus',
                            ];
                            if (isset($filteredResult['related']) && ! empty($filteredResult['related'])) {
                                if ($filteredResult['related'][0]['type'] == 'contact') {
                                    $user = User::where([
                                        'jobnimbus_jnid' => $filteredResult['related'][0]['id'],
                                        'jobnimbus_number' => $filteredResult['related'][0]['number']])
                                        ->first();
                                    if ($user) {
                                        if ($user->position_id == 2) {
                                            $dataCreate['sales_rep_email'] = $user->email;
                                            $dataCreate['sales_rep_name'] = $user->first_name.' '.$user->last_name;
                                        } elseif ($user->position_id == 3) {
                                            $dataCreate['setter_id'] = $user->id;
                                        }
                                    }
                                }
                            }

                            if (isset($filteredResult['status_name']) && $filteredResult['status_name'] == 'Contract Signed') {
                                $dataCreate['customer_signoff'] = date('Y-m-d', $filteredResult['date_status_change']);
                            } elseif (isset($filteredResult['status_name']) && $filteredResult['status_name'] == 'Job Approved') {
                                $dataCreate['m1_date'] = date('Y-m-d', $filteredResult['date_status_change']);
                            } elseif (isset($filteredResult['status_name']) && $filteredResult['status_name'] == 'Complete') {
                                $dataCreate['m2_date'] = date('Y-m-d', $filteredResult['date_status_change']);
                            } elseif (isset($filteredResult['status_name']) && $filteredResult['status_name'] == 'Lost-Cancelled') {
                                $dataCreate['date_cancelled'] = date('Y-m-d', $filteredResult['date_status_change']);
                            }

                            $response = jobnimbus_create_raw_data_history_api($dataCreate);
                            if (! empty($response['new_pid_null'])) {
                                $new_pids[] = $response['new_pid_null'];
                            }
                            if (! empty($response['updated_pid_null'])) {
                                $updated_pids[] = $response['updated_pid_null'];
                            }
                            jobnimbus_create_update_legacy_api_data_null($dataCreate);
                            $api_data[] = $dataCreate;
                            $total_records++;

                        }
                    }

                }

                // $response = json_encode($api_data,JSON_FORCE_OBJECT);
                // $file = 'JobNimbus_data_'.date('Y-m-d_H_i_s').'.json';
                // //Storage::disk('public')->put($file, $response);
                // //s3 bucket in upload file -----
                // $filePath = config('app.domain_name').'/'."JobNimbus-raw-data-files/" . $file;
                // s3_upload($filePath,$response);
                //    //Storage::disk("s3_private")->put($filePath, $response);
                // //s3 bucket in upload file End--------

                // $new_pids = array_filter($new_pids, 'strlen');
                // $updated_pids = array_filter($updated_pids, 'strlen');

                // $count_new_pids = count($new_pids);
                // $count_updated_pids = count($updated_pids);

                // $sheet_data = [
                //     'crm_id' => 4,
                //     'week' =>date('W'),
                //     'week_date'=>date('Y-m-d'),
                //     'month' => date('m'),
                //     'year'=>date('Y'),
                //     'no_of_records'=>$total_records,
                //     'new_records'=>$count_new_pids,
                //     'new_pid'=> '',
                //     'updated_records'=>$count_updated_pids,
                //     'errors' => '',
                //     'log_file_name' => $file,
                //     'status_json' => json_encode(['new_pids'=>$new_pids,'updated_pids'=>$updated_pids])
                // ];

                // // Log::info('sheet data '. json_encode($sheet_data));
                // LegacyWeeklySheet::create($sheet_data);

                echo '==';

            }
            jobnimbus_insert_update_sale_master();
            echo ' sync jobs Successfully ';

        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}
