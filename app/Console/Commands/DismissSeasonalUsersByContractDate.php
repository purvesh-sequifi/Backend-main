<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\SeasonalUsersLog;
use App\Models\User;
use App\Models\UserProfileHistory;
use Exception;
use Illuminate\Console\Command;

class DismissSeasonalUsersByContractDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seasonalUsers:dismissByContractDate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dismiss users at the end of contract date.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $contractEnded = contractEndedUsers();
        $users = User::whereIn('id', $contractEnded)->where(['contract_ended' => 0])->get();
        foreach ($users as $user) {
            try {
                $payroll = Payroll::where(['user_id' => $user->id, 'status' => 1])->first();
                if ($payroll) {
                    $seasonalUsersLog = new SeasonalUsersLog;
                    $seasonalUsersLog->api = 'scheduled job - seasonalUsers:dismissByContractDate';
                    $seasonalUsersLog->response = $payroll;
                    $seasonalUsersLog->col1 = 'Employee has payroll values, skipping dismissal';
                    $seasonalUsersLog->save();

                    continue;
                }

                $user->status_id = 2;
                $user->contract_ended = 1;
                $user->rehire = 1;
                UserProfileHistory::create([
                    'user_id' => $user->id,
                    'updated_by' => 1,
                    'field_name' => 'dismiss',
                    'old_value' => '0',
                    'new_value' => '1',
                ]);

                UserProfileHistory::create([
                    'user_id' => $user->id,
                    'updated_by' => 1,
                    'field_name' => 'status_id',
                    'old_value' => '1',
                    'new_value' => '2',
                ]);
                $user->save();
            } catch (Exception $e) {
                $seasonalUsersLog = new SeasonalUsersLog;
                $seasonalUsersLog->api = 'scheduled job - seasonalUsers:dismissByContractDate';
                $seasonalUsersLog->response = $e;
                $seasonalUsersLog->col1 = 'Exception';
                $seasonalUsersLog->save();
            }
        }
    }
}
