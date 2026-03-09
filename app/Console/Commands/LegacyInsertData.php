<?php

namespace App\Console\Commands;

use App\Core\Traits\SubroutineListTrait;
use App\Models\Crms;
use App\Models\LegacyWeeklySheet;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyInsertData extends Command
{
    use SubroutineListTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:insert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert legacy data in table from api';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $total_records = 0;
        $new_pids = [];
        $updated_pids = [];

        // CHANGING EMAIL ID UPPER CASE TO LOWER CASE, IT USES IN USER RELATIONS.
        User::query()->update(['email' => DB::raw('LOWER(email)')]);

        $crms = Crms::where(['id' => 1, 'status' => 1])->first();
        if (empty($crms)) {
            echo 'Your Legacy setting is not active';
            exit;
        }

        // Sale Can't be updated while payroll is being finalized
        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            echo 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.';
            exit;
        }

        // When Legacy Is Working Then Finalize Can't Happen
        $legacySheet = LegacyWeeklySheet::create([
            'crm_id' => 1,
            'status_json' => json_encode(['new_pids' => $new_pids, 'updated_pids' => []]),
            'in_process' => '1',
        ]);

        // total page get from legacy api
        $tokens = '7449641862621b630406306e560a4fec754d4242';
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            [
                CURLOPT_URL => 'https://lgcy-analytics.com/api/sales_partners/accounts',
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
        $res = json_decode($response);
        $pages = $res->total_pages;

        curl_close($ch);

        for ($i = 1; $i <= $pages; $i++) {
            $ch = curl_init();
            curl_setopt_array(
                $ch,
                [
                    CURLOPT_URL => 'https://lgcy-analytics.com/api/sales_partners/accounts?page='.$i, // your preferred url
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
            $api_data[] = json_decode($response);
            $res = (object) json_decode($response);

            $newData = $res->results;
            foreach ($newData as $val) {
                $response = create_raw_data_history_api($val);
                if (! empty($response['new_pid_null'])) {
                    $new_pids[] = $response['new_pid_null'];
                }
                if (! empty($response['updated_pid_null'])) {
                    $updated_pids[] = $response['updated_pid_null'];
                }
                insert_update_legacy_null_data($val);
                $total_records++;
            }
            curl_close($ch);
        }
        insert_update_sale_master(); // Direct Execute

        $response = json_encode($api_data, JSON_FORCE_OBJECT);
        $file = 'Legacy_data_'.date('Y-m-d_H_i_s').'.json';
        // s3 bucket in upload file -----
        $filePath = config('app.domain_name').'/'.'legacy-raw-data-files/'.$file;
        s3_upload($filePath, $response);
        // s3 bucket in upload file End--------

        $new_pids = array_filter($new_pids, 'strlen');
        $updated_pids = array_filter($updated_pids, 'strlen');

        $count_new_pids = count($new_pids);
        $count_updated_pids = count($updated_pids);

        LegacyWeeklySheet::where('id', $legacySheet->id)->update([
            'crm_id' => 1,
            'week' => date('W'),
            'week_date' => date('Y-m-d'),
            'month' => date('m'),
            'year' => date('Y'),
            'no_of_records' => $total_records,
            'new_records' => $count_new_pids,
            'new_pid' => '',
            'updated_records' => $count_updated_pids,
            'errors' => '',
            'log_file_name' => $file,
            'status_json' => json_encode(['new_pids' => $new_pids, 'updated_pids' => $updated_pids]),
            'in_process' => '0',
        ]);

        $this->call('generate:alert');
        exit('Legacy Insert Data Execute Successfully.');
    }
}
