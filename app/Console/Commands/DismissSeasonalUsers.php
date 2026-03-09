<?php

namespace App\Console\Commands;

use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\Payroll;
use App\Models\SeasonalUsersLog;
use App\Models\User;
use App\Models\UserProfileHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DismissSeasonalUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seasonalUsers:dismiss';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dismiss users at the end of season who joined for a season between October 1st and September 30th.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        $previousYear = Carbon::now()->subYear()->year;
        $currentYear = Carbon::now()->year;

        $seasonStartDate = Carbon::create($previousYear, 10, 1); // October 1st
        $seasonEndDate = Carbon::create($currentYear, 9, 30);

        $users = User::with('state')
            ->whereNotNull('end_date') // Ensure end_date is not null
            ->whereBetween('period_of_agreement_start_date', [$seasonStartDate->format('Y-m-d'), $seasonEndDate->format('Y-m-d')])
            ->whereBetween('end_date', [$seasonStartDate->format('Y-m-d'), $seasonEndDate->format('Y-m-d')])
            ->where('terminate', 0) // only non terminated users can dismiss
            ->where('dismiss', 0)
            ->get();

        dump($seasonStartDate->format('Y-m-d'));
        dump($seasonEndDate->format('Y-m-d'));

        if ($users->isNotEmpty()) {

            foreach ($users as $user) {

                try {

                    dump($user->id);

                    // DB::beginTransaction();

                    $payroll = Payroll::where([
                        'user_id' => $user->id,
                        'status' => 1,
                    ])->first();

                    if ($payroll) {
                        dump('Employee has payroll values, skipping dismissal');
                        // Employee have some payroll values you can not dismiss.
                        // Employee has payroll values, skipping dismissal
                        $SeasonalUsersLog = new SeasonalUsersLog;
                        $SeasonalUsersLog->api = 'scheduled job - seasonalUsers:dismiss';
                        $SeasonalUsersLog->response = $payroll;
                        $SeasonalUsersLog->col1 = 'Employee has payroll values, skipping dismissal';
                        $SeasonalUsersLog->save();

                        continue;
                    }

                    // Inactive
                    $user->status_id = 2; // add new status terminate
                    // Dismiss
                    $user->dismiss = 1;

                    // update status in hubspot

                    $CrmData = Crms::where('id', 2)->where('status', 1)->first();
                    $CrmSetting = CrmSetting::where('crm_id', 2)->first();
                    if (! empty($CrmData) && ! empty($CrmSetting)) {
                        $val = json_decode($CrmSetting['value']);
                        $token = $val->api_key;
                        if (! empty($user->aveyo_hs_id)) {
                            $Hubspotdata['properties'] = ['status' => 'Dismiss'];
                            $this->update_employees($Hubspotdata, $token, $user->id, $user->aveyo_hs_id);
                        }
                    }

                    // update history
                    UserProfileHistory::create([
                        'user_id' => $user->id,
                        'updated_by' => 1, // superadmin id
                        'field_name' => 'dismiss',
                        'old_value' => '0',
                        'new_value' => '1',
                    ]);
                    UserProfileHistory::create([
                        'user_id' => $user->id,
                        'updated_by' => 1, // superadmin id
                        'field_name' => 'status_id',
                        'old_value' => '1',
                        'new_value' => '2',
                    ]);

                    $user->save();

                    // Del Lead
                    // Del OnboardingEmployees

                    dump('done');

                    // DB::commit();

                } catch (Exception $e) {

                    dump($e->getMessage());

                    $SeasonalUsersLog = new SeasonalUsersLog;
                    $SeasonalUsersLog->api = 'scheduled job - seasonalUsers:dismiss';
                    $SeasonalUsersLog->response = $e;
                    $SeasonalUsersLog->col1 = 'Exception';
                    $SeasonalUsersLog->save();

                    // DB::rollBack();

                }

            }

        } else {
            dump('no users');
        }

        return Command::SUCCESS;
    }

    public function update_employees($Hubspotdata, $token, $user_id, $aveyoid)
    {
        // $url = "https://api.hubapi.com/crm/v3/objects/contacts";
        $url = "https://api.hubapi.com/crm/v3/objects/sales/$aveyoid";
        $Hubspotdata = json_encode($Hubspotdata);
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Bearer '.$token,
        ];

        $curl_response = $this->curlRequestDataUpdate($url, $Hubspotdata, $headers, 'PATCH');

        $resp = json_decode($curl_response, true);

        if (count($resp) > 0) {
            $hs_object_id = $resp['properties']['hs_object_id'];
            // $email = $resp['properties']['email'];
            $updateuser = User::where('id', $user_id)->first();
            if ($updateuser) {
                $updateuser->aveyo_hs_id = $hs_object_id;
                $updateuser->save();
            }
        }

    }

    public function curlRequestDataUpdate($url, $Hubspotdata, $headers, $method = 'PATCH')
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
            CURLOPT_POSTFIELDS => $Hubspotdata,
            CURLOPT_HTTPHEADER => $headers,

        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;

    }
}
