<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserAttendanceDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserAttendanceSet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'userAttendanceSet:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically check out users who forgot to check out and send an email notification';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->checkoutupdated();
            $checkedins = UserAttendance::with('userattendancelist')->where('is_synced', 0)->get();
            // Log::info($checkedins,1);
            Log::info('checkedins -'.$checkedins);
            foreach ($checkedins as $checkdata) {
                if (isset($checkdata) && $checkdata->userattendancelist) {
                    $totallunch = 0;
                    $totalBreak = 0;
                    $totallunchBreakTime = 0;
                    $checkedin = $checkdata->userattendancelist;
                    foreach ($checkedin as $index => $timeslist) {
                        if ($timeslist->type == 'clock in') {
                            $val['checkin'] = $timeslist->attendance_date;
                            $timein = new Carbon($timeslist->attendance_date);
                        } elseif ($timeslist->type == 'lunch') {
                            $lunchstart = new Carbon($timeslist->attendance_date);
                            $lunchend = new Carbon($checkedin[$index + 1]->attendance_date);
                            $totallunch += $lunchstart->diffInSeconds($lunchend);
                        } elseif ($timeslist->type == 'break') {
                            $breakstart = new Carbon($timeslist->attendance_date);
                            $breakend = new Carbon($checkedin[$index + 1]->attendance_date);
                            $totalBreak += $breakstart->diffInSeconds($breakend);
                        } elseif ($timeslist->type == 'clock out') {
                            $val['checkout'] = $timeslist->attendance_date;
                            $timeout = new Carbon($timeslist->attendance_date);
                        }
                    }
                    $totallunchBreakTime = $totallunch + $totalBreak;
                    $totalHoursWorkedsec = $timein->diffInSeconds($timeout);
                    $netMinutesWorked = 0;
                    $netMinutesWorked = $totalHoursWorkedsec - $totallunchBreakTime;
                    /*$tudata['current_time'] = gmdate('H:i:s', $netMinutesWorked);
                    $tudata['lunch_time'] = gmdate('H:i:s', $totallunch);
                    $tudata['break_time'] = gmdate('H:i:s', $totalBreak);*/

                    $tudata['current_time'] = $this->hoursformat($netMinutesWorked);
                    $tudata['lunch_time'] = $this->hoursformat($totallunch);
                    $tudata['break_time'] = $this->hoursformat($totalBreak);

                    $tudata['updated_at'] = NOW();
                    $tudata['is_synced'] = 1;
                    UserAttendance::where('id', $checkdata->id)->update($tudata);
                }
            }
            Log::info('UserAttendance updated');
        } catch (Exception $e) {
            Log::info('UserAttendance update'.$e->getMessage());
        }
    }

    protected function hoursformat($seconds)
    {
        $thours = intdiv($seconds, 3600); // Get the hours part
        $tminutes = intdiv($seconds % 3600, 60); // Get the minutes part
        $tseconds = $seconds % 60; // Get the remaining seconds part

        return sprintf('%02d:%02d:%02d', $thours, $tminutes, $tseconds);
    }

    protected function checkoutupdated()
    {
        $userAttendances = UserAttendance::with('userattendancelist')
            ->where('is_synced', 0)
            ->whereDoesntHave('userattendancelist', function ($query) {
                $query->where('type', 'clock out');
            })
            ->get();
        // Log::info('UserAttendance updated -'.$userAttendances);
        // die();
        foreach ($userAttendances as $userAttendance) {
            $clockintime = $userAttendance->userattendancelist[0];
            $checkouttime = Carbon::parse($clockintime->attendance_date)->addHours(24);

            $this->checkBLtime($userAttendance->userattendancelist, $checkouttime);
            $uataadd = [
                'user_attendance_id' => $userAttendance->id,
                'office_id' => $clockintime->office_id,
                'type' => 'clock out',
                'entry_type' => 'Auto',
                'attendance_date' => $checkouttime,
            ];
            UserAttendanceDetail::create($uataadd);
            // Send email notification to the user
            $user_id = $userAttendance->user_id; // Assuming the relationship exists
            $user_info = User::where('id', $user_id)->first();
            /*Mail::raw(
                "Dear {$user_info->first_name},\n\nYou were automatically checked out at the end of the day because you forgot to check out.\n\nBest regards,\nYour Attendance System",
                function ($message) use ($user_info) {
                    $message->to($user_info->email)
                            ->subject('Automatic Checkout Notification');
                }
            );*/
        }
    }

    protected function checkBLtime($times, $utime)
    {
        Log::info('times -'.$times);
        // $times = $times->where('attendance_date','0000-00-00 00:00:00');
        foreach ($times as $key => $time) {
            if ($time->attendance_date == '0000-00-00 00:00:00') {
                if ($times[$key - 1]->attendance_date) {
                    $utime = Carbon::parse($times[$key - 1]->attendance_date)->addMinutes(0);
                }
                UserAttendanceDetail::where('id', $time->id)->update(['attendance_date' => $utime, 'entry_type' => 'Auto']);
            }

        }

        return true;
    }
}
