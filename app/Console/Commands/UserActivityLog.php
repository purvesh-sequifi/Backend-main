<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserActivityLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'userActivitylogs:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'user activity logs run';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        exec('echo "" > '.storage_path('logs/user_activity.log'));
        $userActivityLogs = DB::table('activity_log')->get();
        $data = [];
        foreach ($userActivityLogs as $key => $logs) {
            $action = DB::table('users')->where('id', $logs->causer_id)->first();
            $fname = isset($action->first_name) ? $action->first_name : ' ';
            $lname = isset($action->last_name) ? $action->last_name : ' ';
            if ($logs->subject_type == \App\Models\User::class) {
                $change = DB::table('users')->where('id', $logs->subject_id)->first();
                $emp = $change->first_name.' '.$change->last_name;
            } else {
                $replace = str_replace("App\Models", ' ', $logs->subject_type);
                $emp = $replace;
            }
            // Laravel log
            $data['user_id'] = $logs->causer_id;
            $data['action_by'] = $fname.' '.$lname;
            $data['log_name'] = $logs->log_name;
            $data['description'] = $logs->description;
            $data['subject'] = $logs->subject_type;
            $data['changes_id'] = $logs->subject_id;
            $data['changes_event'] = $emp;
            $data['event'] = $logs->event;
            $data['properties'] = json_decode($logs->properties);
            Log::channel('user_activity_log')->info('User Activity Logs', [$data]);
        }
    }
}
