<?php

namespace App\Http\Controllers\API\Emptimercard;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Core\Traits\PayRollCommissionTrait;
use App\Http\Controllers\Controller;
use App\Jobs\Payroll\CalculateSalaryJob;
use App\Models\AdditionalPayFrequency;
use App\Models\ApprovalsAndRequest;
use App\Models\FrequencyType;
use App\Models\MonthlyPayFrequency;
use App\Models\PayrollHourlySalary;
use App\Models\PayrollOvertime;
use App\Models\PositionPayFrequency;
use App\Models\PositionWage;
use App\Models\SchedulingApprovalSetting;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserAttendanceDetail;
use App\Models\UserOrganizationHistory;
use App\Models\UserSchedule;
use App\Models\UserScheduleDetail;
use App\Models\UserWagesHistory;
use App\Models\WeeklyPayFrequency;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmptimercardController extends Controller
{
    use EvereeTrait;
    use PayFrequencyTrait;
    use PayRollCommissionTrait;

    public function today_card(Request $request): JsonResponse
    {
        $office_id = $request->office_id ?? 0;
        $time = $request->time ?? Now();
        $date = $request->date ?? Now();
        $status = true;
        $statuscode = 200;
        $message = '';
        try {
            $pdata['date'] = $date;
            $pdata['time'] = $time;
            $mydata = $this->mycard($pdata);
            $user_id = Auth::user()->id;
            $user = User::with('state')->where('id', $user_id)->firstOrFail();
            if (isset($user->image) && $user->image != null) {
                $user->image_path = s3_getTempUrl(config('app.domain_name').'/'.$user->image);
            } else {
                $user->image_path = null;
            }
            $mydata['user'] = $user;
            $todays = UserAttendance::where('user_id', $user_id)->whereDate('date', $pdata['time'])->first();
            if ($todays) {
                $breaks = UserAttendanceDetail::where('user_attendance_id', $todays->id)->get();
                $todays->times = $breaks;
            }

            $mydata['today_log'] = $todays;

            // echo "<pre>";print_r($mydata);di
            return response()->json([
                'ApiName' => 'today_card',
                'status' => $status,
                // 'message' => $message,
                'data' => $mydata,
            ], $statuscode);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'today_card',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function timer_add(Request $request): JsonResponse
    {
        // Validate the request data
        $validatedData = $request->validate([
            'type' => 'required',
            // 'time'=>'required',
            'office_id' => 'nullable|exists:locations,id',
        ]);

        $type = $request->type ?? '';
        $office_id = $request->office_id ?? 0;
        $time = $request->time ? (new DateTime($request->time)) : NOW();
        $date = $request->date ?? NOW();
        $optional = $request->optional ?? '';
        //  echo $time;die();
        $pdata['type'] = $type;
        $pdata['office_id'] = $office_id;
        $pdata['time'] = $time;
        $pdata['date'] = $date;
        $pdata['optional'] = $optional;
        $status = true;
        $statuscode = 200;
        $message = '';
        try {
            if ($type == 'checkin') {
                $response = $this->checkIn($pdata);
                if (! $response['status']) {
                    $statuscode = 400;
                }
                $message = $response['msg'];
            } elseif ($type == 'start_lunch' || $type == 'start_break') {
                $response = $this->startbreak($pdata);
                if (! $response['status']) {
                    $statuscode = 400;
                    $status = false;
                }
                $message = $response['msg'];
            } elseif ($type == 'stop_lunch' || $type == 'stop_break') {
                $response = $this->endbreak($pdata);
                if (! $response['status']) {
                    $statuscode = 400;
                    $status = false;
                }
                $message = $response['msg'];
            } elseif ($type == 'checkout') {
                $response = $this->checkout($pdata);
                if (! $response['status']) {
                    $statuscode = 400;
                    $status = false;
                }
                $message = $response['msg'];
            } else {
                $message = 'Invalid action';
                $statuscode = 400;

            }
            $mydata = $this->mycard($pdata);

            return response()->json([
                'ApiName' => 'timer_add',
                'status' => $status,
                'message' => $message,
                'data' => $mydata,
            ], $statuscode);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'timer_add',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    protected function mycard($data)
    {
        $user_id = Auth::user()->id;
        $currentstate = 'Notcheckin';
        $totalHoursWorked = 0;
        $netMinutesWorked = 0;
        $totallunch = 0;
        $totalBreak = 0;
        $totalearning = 0;
        $val = [];
        $checkedin = UserAttendance::where('user_id', $user_id)->whereDate('date', $data['time'])->first();

        if ($checkedin) {
            $timeslists = UserAttendanceDetail::where('user_attendance_id', $checkedin->id)->get();
            $totallunch = 0;
            $totalBreak = 0;
            $totallunchBreakTime = 0;
            $timein = 0;
            $timeout = 0;
            foreach ($timeslists as $index => $timeslist) {
                if ($timeslist->type == 'clock in') {
                    $val['checkin'] = $timeslist->attendance_date;
                    $timein = new Carbon($timeslist->attendance_date);
                } elseif ($timeslist->type == 'lunch') {
                    $lunchstart = new Carbon($timeslist->attendance_date);
                    $endtime = $timeslists[$index + 1]->attendance_date;
                    $endtime = $endtime != '0000-00-00 00:00:00' ? $endtime : NOW();
                    $lunchend = new Carbon($endtime);
                    $totallunch += $lunchstart->diffInSeconds($lunchend);
                } elseif ($timeslist->type == 'break') {
                    $breakstart = new Carbon($timeslist->attendance_date);
                    $endtime = $timeslists[$index + 1]->attendance_date;
                    $endtime = $endtime != '0000-00-00 00:00:00' ? $endtime : NOW();
                    $breakend = new Carbon($endtime);
                    $totalBreak += $breakstart->diffInSeconds($breakend);
                } elseif ($timeslist->type == 'clock out') {
                    $val['checkout'] = $timeslist->attendance_date;
                    $timeout = new Carbon($timeslist->attendance_date);
                }
            }
            $totallunchBreakTime = $totallunch + $totalBreak;
            $totalHoursWorkedsec = $timein->diffInSeconds($timeout);
            $currentstatetime = $val['checkin'];
            $currentstate = 'checkin';

            // Calculate net hours worked
            $netMinutesWorked = 0;
            $netMinutesWorked = $totalHoursWorkedsec - $totallunchBreakTime;
            $getcurrentstate = UserAttendanceDetail::where('user_attendance_id', $checkedin->id)->where('attendance_date', '0000-00-00 00:00:00')->latest()->first();
            if ($getcurrentstate) {
                $getcst = UserAttendanceDetail::where('user_attendance_id', $checkedin->id)->where('id', $getcurrentstate->id - 1)->first();
                $currentstate = $getcst ? $getcst->type : '';
                $currentstatetime = $getcst ? $getcst->attendance_date : '';
            } else {
                $getcurrentstatec = UserAttendanceDetail::where('user_attendance_id', $checkedin->id)->where('type', 'clock out')->first();
                $currentstate = $getcurrentstatec ? $getcurrentstatec->type : $currentstate;
                $currentstatetime = $getcurrentstatec ? $getcurrentstatec->attendance_date : $currentstatetime;
            }
            // Calculate Today earning
            $pay_type = Auth::user()->pay_type;
            $pay_rate = Auth::user()->pay_rate;
            $pay_rate_type = Auth::user()->pay_rate_type;
            if ($pay_rate > 0 && ! empty($pay_rate_type)) {
                switch ($pay_type) {
                    case 'Hourly':
                        // $totalearning = number_format(((($netMinutesWorked / 60)/60)*(Auth::user()->pay_rate)),2);
                        $dailyPay = $this->calculateHourlyDailyPay($pay_rate, $pay_rate_type);
                        $totalearning = number_format(((($netMinutesWorked / 60) / 60) * $dailyPay), 2);
                        break;

                    case 'Salary':
                        $dailyPay = $this->calculateSalaryDailyPay($pay_rate, $pay_rate_type);
                        $totalearning = number_format($dailyPay, 2);
                        break;

                    default:
                        $totalearning = 0; // Handle invalid pay types if needed
                        break;
                }
            }

            if ($currentstate != '') {
                if ($currentstate == 'clock out') {
                    $currentstate = 'checkout';
                } elseif ($currentstate == 'clock in') {
                    $currentstate = 'checkin';
                }
            }

            $val['current_state_start_time'] = $currentstatetime;
            $val['current_state'] = $currentstate;

            $totalHoursWorked = $this->hoursformat($totalHoursWorkedsec);
            $netMinutesWorked = $this->hoursformat($netMinutesWorked);
            $totallunch = $this->hoursformat($totallunch);
            $totalBreak = $this->hoursformat($totalBreak);
        }

        //

        $val['Total_hour'] = $totalHoursWorked != 0 ? $totalHoursWorked : '00:00:00';
        $val['Total_hour_work'] = $netMinutesWorked != 0 ? $netMinutesWorked : '00:00:00';
        $val['Total_lunch'] = $totallunch != 0 ? $totallunch : '00:00:00';
        $val['Total_break'] = $totalBreak != 0 ? $totalBreak : '00:00:00';
        $val['Total_earning'] = $totalearning;

        // echo "<pre>";print_r($val);die();
        return $val;
        // return $existingCheckIn;
    }

    protected function checkIn($data)
    {
        $user_id = Auth::user()->id;
        $type = $data['type'];
        $existingCheckIn = UserAttendance::where('user_id', $user_id)->whereDate('date', '=', $data['time'])->first();

        if ($existingCheckIn) {
            $rdata['msg'] = 'You already checked in today';
            $rdata['status'] = false;
        } else {
            $dataadd = [
                'user_id' => $user_id,
                'date' => $data['time']];
            $userattendance = UserAttendance::create($dataadd);
            $uattid = $userattendance->id;
            $uataadd = [
                'user_attendance_id' => $uattid,
                'office_id' => $data['office_id'],
                'type' => 'clock in',
                'attendance_date' => $data['time'],
            ];
            $this->createattendencehistory($uataadd);
            if (isset($data['office_id'])) {
                // $this->scheduletimeupdateid($userattendance,$data['office_id']);
                $this->scheduletimeupdateid($userattendance, $data['office_id'], $data['time']);
            }

            dispatch(new CalculateSalaryJob($user_id, Auth::user()->id))->afterResponse();
            $rdata['msg'] = 'Check-in successful';
            $rdata['status'] = true;
        }

        return $rdata;
    }

    protected function checkout($data)
    {
        $rdata['msg'] = 'try again';
        $rdata['status'] = false;
        $checkedinout = $this->checkedinout($data);

        if ($checkedinout['status']) {
            $existcheckin = $checkedinout['data'];
            if (isset($existcheckin->id)) {
                $existingtime = UserAttendanceDetail::where('user_attendance_id', $existcheckin->id)->where('attendance_date', '0000-00-00 00:00:00')->latest()->first();
                $rdata['status'] = true;
                if (! empty($existingtime)) {
                    $rdata['msg'] = 'Priority '.$existingtime->type;
                    $rdata['status'] = false;
                }
                if ($rdata['status'] && $existcheckin->id) {
                    $uataadd = [
                        'user_attendance_id' => $existcheckin->id,
                        'office_id' => $data['office_id'],
                        'type' => 'clock out',
                        'attendance_date' => $data['time'],
                    ];
                    $this->createattendencehistory($uataadd);
                    $this->timesetcomplete($existcheckin->id);
                    // $this->payroll_wages_create($existcheckin);
                    $rdata['msg'] = 'Check-out successful';
                    $rdata['status'] = true;
                }
            }
        } else {
            $rdata['msg'] = $checkedinout['msg'];
            $rdata['status'] = false;
        }

        return $rdata;
    }

    protected function timesetcomplete($id)
    {
        $checkedins = UserAttendanceDetail::where('user_attendance_id', $id)->get();
        $totallunch = 0;
        $totalBreak = 0;
        $totallunchBreakTime = 0;
        foreach ($checkedins as $index => $timeslist) {
            if (isset($timeslist)) {
                // $checkedin = $checkedins;
                if ($timeslist->type == 'clock in') {
                    $timein = new Carbon($timeslist->attendance_date);
                } elseif ($timeslist->type == 'lunch') {
                    $lunchstart = new Carbon($timeslist->attendance_date);
                    $lunchend = new Carbon($checkedins[$index + 1]->attendance_date);
                    $totallunch += $lunchstart->diffInSeconds($lunchend);
                } elseif ($timeslist->type == 'break') {
                    $breakstart = new Carbon($timeslist->attendance_date);
                    $breakend = new Carbon($checkedins[$index + 1]->attendance_date);
                    $totalBreak += $breakstart->diffInSeconds($breakend);
                } elseif ($timeslist->type == 'clock out') {
                    $timeout = new Carbon($timeslist->attendance_date);
                }
            }
        }
        $totallunchBreakTime = $totallunch + $totalBreak;
        $totalHoursWorkedsec = $timein->diffInSeconds($timeout);
        $netMinutesWorked = 0;
        $netMinutesWorked = $totalHoursWorkedsec - $totallunchBreakTime;
        $tudata['current_time'] = $this->hoursformat($netMinutesWorked);
        $tudata['lunch_time'] = $this->hoursformat($totallunch);
        $tudata['break_time'] = $this->hoursformat($totalBreak);
        $tudata['updated_at'] = NOW();
        $tudata['is_synced'] = 1;
        UserAttendance::where('id', $id)->update($tudata);
    }

    protected function startbreak($data)
    {
        $rdata['msg'] = 'try again';
        $rdata['status'] = false;
        [$type1, $type] = $data['type'] ? explode('_', $data['type']) : '';
        $checkedinout = $this->checkedinout($data);
        if ($checkedinout && $checkedinout['status']) {
            $existcheckin = $checkedinout['data'];
            if (isset($existcheckin->id)) {
                $existingtime = UserAttendanceDetail::where('user_attendance_id', $existcheckin->id)->where('attendance_date', '0000-00-00 00:00:00')->latest()->first();
                $rdata['status'] = true;
                if (! empty($existingtime)) {
                    $rdata['msg'] = 'Priority '.$existingtime->type;
                    $rdata['status'] = false;
                }
                if ($rdata['status']) {
                    $uataadd = [
                        'user_attendance_id' => $existcheckin->id,
                        'office_id' => $data['office_id'],
                        'type' => $type,
                        'attendance_date' => $data['time'],
                    ];
                    $this->createattendencehistory($uataadd);
                    $uataadd['type'] = 'end '.$type;
                    $uataadd['attendance_date'] = '0000-00-00 00:00:00';
                    $this->createattendencehistory($uataadd);
                    $rdata['msg'] = $data['type'].' successful';
                    $rdata['status'] = true;
                }
            }
        } else {
            $rdata['msg'] = $checkedinout['msg'];
            $rdata['status'] = false;
        }

        return $rdata;
    }

    protected function endbreak($data)
    {
        $rdata['msg'] = 'try again';
        $rdata['status'] = false;
        [$type1, $type] = $data['type'] ? explode('_', $data['type']) : '';
        $type = 'end '.$type;
        $checkedinout = $this->checkedinout($data);
        if ($checkedinout && $checkedinout['status']) {
            $existcheckin = $checkedinout['data'];
            if (isset($existcheckin->id)) {
                $existingtime = UserAttendanceDetail::where('user_attendance_id', $existcheckin->id)->where('type', $type)->where('attendance_date', '0000-00-00 00:00:00')->first();
                if (! empty($existingtime)) {
                    $existingtime->update(['attendance_date' => $data['time']]);
                    $rdata['msg'] = $data['type'].' successful';
                    $rdata['status'] = true;
                } else {
                    $rdata['msg'] = 'Missing Start time';
                    $rdata['status'] = false;
                }
            }
        } else {
            $rdata['msg'] = $checkedinout['msg'];
            $rdata['status'] = false;
        }

        return $rdata;
    }

    protected function checkedinout($data, $pastdate = false)
    {
        $user_id = Auth::user()->id;
        $rdata['msg'] = '';
        $rdata['status'] = true;
        $existingCheckIn = UserAttendance::where('user_id', $user_id)->whereDate('date', $data['time'])->first();
        $rdata['data'] = $existingCheckIn;
        if (! $existingCheckIn) {
            $rdata['msg'] = 'You not checked in today';
            $rdata['status'] = false;
            if ($pastdate) {
                $pdate = $data['time']->format('Y-m-d');
                $dateTime = new DateTime($pdate);
                $dateTime->modify('-1 day');
                $pdate = $dateTime->format('Y-m-d');
                // $pexistingCheckIn = UserAttendance::where('user_id', $user_id)->whereDate('date', $data['time'])->first();
                $pexistingCheckIn = UserAttendance::with('userattendancelist')
                    ->where('user_id', $user_id)->whereDate('date', $pdate)
                    ->whereHas('userattendancelist', function ($query) {
                        $query->where('type', 'clock out');
                    })->get();

                // where('user_id', $user_id)->whereDate('date', $data['time'])->first();
                if ($pexistingCheckIn->isEmpty()) {
                    $pexistingCheckIn = UserAttendance::with('userattendancelist')
                        ->where('user_id', $user_id)->whereDate('date', $pdate)->first();
                    $rdata['data'] = $pexistingCheckIn;
                    $rdata['status'] = true;
                } else {
                    $rdata['msg'] = 'You already check-Out';
                    $rdata['status'] = false;
                }

            }
        } else {
            $checkcheckoout = UserAttendanceDetail::where('user_attendance_id', $existingCheckIn->id)->where('type', 'clock out')->first();
            if ($checkcheckoout) {
                $rdata['msg'] = 'You already check-Out in today';
                $rdata['status'] = false;
            }
        }

        return $rdata;
    }

    protected function createattendencehistory($data)
    {
        $data['entry_type'] = 'User';
        $data['created_by'] = Auth::user()->id;
        $rdata = UserAttendanceDetail::create($data);

        return $rdata;
    }

    protected function scheduletimeupdateid($data, $office_id, $attendance_time)
    {
        if (isset($data->id)) {
            $userschedule = UserSchedule::where('user_id', $data->user_id)->first();
            if ($userschedule) {
                $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id', $userschedule->id)->where('office_id', $office_id)->wheredate('schedule_from', $data->date)->first();
                if ($checkUserScheduleDetail) {
                    $checkUserScheduleDetail->where('schedule_id', $userschedule->id)->where('office_id', $office_id)->wheredate('schedule_from', $data->date)->update(['user_attendance_id' => $data->id]);
                } else {
                    $s_date = Carbon::parse($attendance_time);
                    $scheduleTo = $s_date->copy()->addHours(8)->addMinutes(30); // set default 8.5 hours add schedule hours and 30 min lunch
                    Log::info(['s_date before modification' => $s_date->toDateTimeString()]);

                    $endOfDay = $s_date->copy()->endOfDay()->setTime(23, 59, 00);
                    Log::info(['endOfDay===> ' => $endOfDay]);

                    // Check if $scheduleTo exceeds the end of the day
                    if ($scheduleTo->greaterThan($endOfDay)) {
                        // If it exceeds, set $scheduleTo to 23:59:00
                        $scheduleTo = $endOfDay;
                        // Log::info(['scheduleTo 2===> ' => $scheduleTo]);
                    }
                    Log::info(['scheduleTo===> ' => $scheduleTo->toDateTimeString()]);
                    Log::info(['s_date 2===> ' => $s_date->toDateTimeString()]);

                    $dayNumber = $s_date->dayOfWeekIso;
                    $scheduleDetaisData = [
                        'schedule_id' => $userschedule->id,
                        'office_id' => $office_id,
                        'schedule_from' => $attendance_time,
                        // 'schedule_to' => $scheduleTo->toDateTimeString(),
                        'schedule_to' => $scheduleTo,
                        'lunch_duration' => '30 Mins',
                        'work_days' => $dayNumber,
                        'repeated_batch' => 0,
                        'user_attendance_id' => 1,
                    ];
                    // Log::info(['scheduleDetaisData===>' => $scheduleDetaisData]);
                    $dataStored = UserScheduleDetail::create($scheduleDetaisData);
                    // dd($dataStored);
                }
            } else {
                $create_userschedule = UserSchedule::create(['user_id' => $data->user_id, 'scheduled_by' => Auth::user()->id]);
                // dd($create_userschedule);
                if ($create_userschedule) {
                    $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id', $create_userschedule->id)
                        ->where('office_id', $office_id)
                        ->wheredate('schedule_from', $data->date)
                        ->first();
                    if (empty($checkUserScheduleDetail)) {
                        $s_date = Carbon::parse($attendance_time);
                        $scheduleTo = $s_date->copy()->addHours(8)->addMinutes(30); // set default 8.5 hours add schedule hours and 30 min lunch
                        Log::info(['s_date before modification' => $s_date->toDateTimeString()]);

                        $endOfDay = $s_date->copy()->endOfDay()->setTime(23, 59, 00);
                        Log::info(['endOfDay===> ' => $endOfDay]);

                        // Check if $scheduleTo exceeds the end of the day
                        if ($scheduleTo->greaterThan($endOfDay)) {
                            // If it exceeds, set $scheduleTo to 23:59:00
                            $scheduleTo = $endOfDay;
                            // Log::info(['scheduleTo 2===> ' => $scheduleTo]);
                        }
                        Log::info(['scheduleTo===> ' => $scheduleTo->toDateTimeString()]);
                        Log::info(['s_date 2===> ' => $s_date->toDateTimeString()]);

                        $dayNumber = $s_date->dayOfWeekIso;
                        $scheduleDetaisData = [
                            'schedule_id' => $create_userschedule->id,
                            'office_id' => $office_id,
                            'schedule_from' => $attendance_time,
                            // 'schedule_to' => $scheduleTo->toDateTimeString(),
                            'schedule_to' => $scheduleTo,
                            'lunch_duration' => '30 Mins',
                            'work_days' => $dayNumber,
                            'repeated_batch' => 0,
                            'user_attendance_id' => $data->id,
                        ];
                        // Log::info(['scheduleDetaisData===>' => $scheduleDetaisData]);
                        $dataStored = UserScheduleDetail::create($scheduleDetaisData);
                        // dd($dataStored);
                    }
                }
            }
        }
    }

    protected function hoursformat($seconds)
    {
        $thours = intdiv($seconds, 3600); // Get the hours part
        $tminutes = intdiv($seconds % 3600, 60); // Get the minutes part
        $tseconds = $seconds % 60; // Get the remaining seconds part

        return sprintf('%02d:%02d:%02d', $thours, $tminutes, $tseconds);
    }

    public function timesheets(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'pay_period' => 'required',
        ]);

        $time_format = ($request->time_format == '12') ? 'h:i:s A' : 'H:i:s';
        $user_id = Auth::user()->id;
        $user = User::find($user_id);
        $positionId = $user->sub_position_id ? $user->sub_position_id : $user->position_id;

        $positionPayFrequency = PositionPayFrequency::query()->where('position_id', $positionId)->first();
        if ($positionPayFrequency) {
            $type = '';
            if ($positionPayFrequency->frequency_type_id == FrequencyType::WEEKLY_ID) {
                $class = WeeklyPayFrequency::class;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::MONTHLY_ID) {
                $class = MonthlyPayFrequency::class;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
            }

            if (isset($class)) {
                $payFrequencyQuery = $class::query();

                if ($request->pay_period == 'current') {
                    if ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID || $positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                        $payFrequency = $payFrequencyQuery->where('type', $type)->whereRaw('"'.date('Y-m-d').'" between `pay_period_from` and `pay_period_to`')->first();
                    } else {
                        $payFrequency = $payFrequencyQuery->whereRaw('"'.date('Y-m-d').'" between `pay_period_from` and `pay_period_to`')->first();
                    }
                } elseif ($request->pay_period == 'previous') {
                    if ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID || $positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                        $payFrequency = $payFrequencyQuery->where('type', $type)
                            ->where('pay_period_to', '<', date('Y-m-d'))
                            ->orderBy('pay_period_to', 'desc')
                            ->first();
                    } else {
                        $payFrequency = $payFrequencyQuery->where('pay_period_to', '<', date('Y-m-d'))
                            ->orderBy('pay_period_to', 'desc')
                            ->first();
                    }
                } else {
                    return response()->json([
                        'ApiName' => 'your_earnings',
                        'status' => false,
                        'message' => 'Invalid pay period',
                        'data' => [],
                    ], 400);
                }

                if (! $payFrequency) {
                    return response()->json([
                        'ApiName' => 'your_earnings',
                        'status' => false,
                        'message' => 'Pay period not found',
                        'data' => [],
                    ], 400);
                }

                $start = new DateTime($payFrequency->pay_period_from);
                $end = new DateTime($payFrequency->pay_period_to);
                $end->modify('+1 day'); // to include the end date in the period

                $interval = new DateInterval('P1D'); // 1 day interval
                $daterange = new DatePeriod($start, $interval, $end);

                // Collect all dates in an array
                $dates = [];
                foreach ($daterange as $date) {
                    $dates[] = $date->format('Y-m-d');
                }

                // Fetch all user attendances in one query
                $userAttendances = UserAttendance::with('userattendancelist')
                    ->where('user_id', $user_id)
                    ->whereIn('date', $dates)
                    ->get()
                    ->keyBy('date'); // Key by date for easy lookup

                $userSchedule = UserSchedule::with('userSchedulelist')->where('user_id', $user_id)->first();

                $dateArray = [];
                foreach ($dates as $key => $date) {
                    $dateObj = Carbon::parse($date);
                    $sche_from = null;
                    $sche_to = null;

                    $scheduled = [];

                    // Schedule
                    if ($userSchedule) {
                        foreach ($userSchedule->userSchedulelist as $schedule) {
                            $day_name = $this->getDayName($schedule->work_days);
                            $schedule_from = Carbon::parse($schedule->schedule_from);
                            $schedule_to = Carbon::parse($schedule->schedule_to);

                            if ($schedule_from->format('Y-m-d') == $dateObj->format('Y-m-d')) {
                                $scheduled[$key] = [
                                    'schedule_from' => $schedule_from->format($time_format) == '00:00:00' ? null : $schedule_from->format($time_format),
                                    'schedule_to' => $schedule_to->format($time_format) == '00:00:00' ? null : $schedule_to->format($time_format),
                                    'is_flexible' => $schedule->is_flexible ? $schedule->is_flexible : 0,
                                ];
                            }
                        }
                    }
                    // $date = '2024-10-08';
                    if (isset($userAttendances[$date])) {
                        $attendance = $userAttendances[$date];
                        $attendanceList = $attendance->userattendancelist->sortBy('attendance_date')->values()->all();

                        for ($i = 0; $i < count($attendanceList); $i++) {
                            $current = $attendanceList[$i];
                            $next = $attendanceList[$i + 1] ?? null;

                            $attendance_time = null;

                            if ($next && $next->entry_type != 'Auto' && $next->attendance_date != '0000-00-00 00:00:00' && $current->attendance_date != '0000-00-00 00:00:00') {
                                $next->attendance_date = $next->attendance_date ?? null;
                                $currentDateTime = new DateTime($current->attendance_date);
                                $nextDateTime = new DateTime($next->attendance_date);
                                $interval = $currentDateTime->diff($nextDateTime);
                                $attendance_time = $interval->format('%H:%I:%S');
                            }
                            if (! empty($current->type) || $current->type != '') {
                                $current->attendance_type = $this->mapAttendanceType($current->type);
                            } else {
                                $current->attendance_type = $this->mapAttendanceType($current->entry_type);
                            }

                            $current->attendance_time = $attendance_time;
                            if ($current->attendance_type == 'Adjustment') {
                                $adjustment = ApprovalsAndRequest::where('id', $current->adjustment_id)->first();
                                $clock_in = new DateTime($adjustment->clock_in);
                                $clock_out = new DateTime($adjustment->clock_out);
                                $clock_interval = $clock_in->diff($clock_out);
                                $clockIn_interval = (($clock_interval->h * 3600) + ($clock_interval->i * 60) + $clock_interval->s) - (($adjustment->lunch_adjustment * 60) + ($adjustment->break_adjustment * 60));
                                $attendance_time = $this->hoursformat($clockIn_interval);
                                $current->attendance_time = $attendance_time;
                                $current->lunch_time_time = $this->hoursformat($adjustment->lunch_adjustment * 60);
                                $current->break_time = $this->hoursformat($adjustment->break_adjustment * 60);
                            }
                        }

                        $dateArray[]['day'] = [
                            'id' => $attendance->id,
                            'user_id' => $attendance->user_id,
                            'current_time' => $attendance->current_time,
                            'lunch_time' => $attendance->lunch_time,
                            'break_time' => $attendance->break_time,
                            'is_synced' => $attendance->is_synced,
                            'date' => $attendance->date,
                            'status' => $attendance->status,
                            'deleted_at' => $attendance->deleted_at,
                            'userattendancelist' => $attendanceList,
                        ];
                        // dump($dateArray);
                    } else {
                        $scheduled_time = 0;
                        $is_flexible = isset($scheduled[$key]) ? $scheduled[$key]['is_flexible'] : false;
                        // if(isset($scheduled)){
                        // $schedule_from = Carbon::parse($schedule['schedule_from']);
                        // $schedule_to = Carbon::parse($schedule['schedule_to'] );
                        $adjustment = ApprovalsAndRequest::where('user_id', $user_id)
                            ->where('adjustment_date', '=', $date)
                            ->where('adjustment_date', '<=', date('Y-m-d'))
                            ->where('adjustment_type_id', 9)
                            ->where('status', 'Approved')
                            ->first();
                        if ($dateObj->format('Y-m-d') < Carbon::today()->format('Y-m-d') && ! empty($scheduled[$key]) && ! $adjustment) {

                            $sche_from = Carbon::parse($scheduled[$key]['schedule_from']);
                            $sche_to = Carbon::parse($scheduled[$key]['schedule_to']);
                            $seconds = $sche_from->diffInSeconds($sche_to);
                            $absent_time = $this->hoursformat($seconds);
                            $dateArray[]['day'] = [
                                'date' => $date,
                                'userattendancelist' => [
                                    'attendance_type' => 'absent',
                                    'attendance_time' => $absent_time,
                                ],
                            ];

                        } elseif ($adjustment) {
                            $clock_in = new DateTime($adjustment->clock_in);
                            $clock_out = new DateTime($adjustment->clock_out);
                            $clock_interval = $clock_in->diff($clock_out);
                            $clockIn_interval = (($clock_interval->h * 3600) + ($clock_interval->i * 60) + $clock_interval->s) - (($adjustment->lunch_adjustment * 60) + ($adjustment->break_adjustment * 60));
                            $attendance_time = $this->hoursformat($clockIn_interval);
                            $adjustment->attendance_time = $attendance_time;
                            $adjustment->lunch_time_time = $this->hoursformat($adjustment->lunch_adjustment * 60);
                            $adjustment->break_time = $this->hoursformat($adjustment->break_adjustment * 60);
                            $adjustment->attendance_type = 'Adjustment';

                            $dateArray[]['day'] = [
                                'id' => null,
                                'user_id' => null,
                                'current_time' => null,
                                'lunch_time' => null,
                                'break_time' => null,
                                'is_synced' => null,
                                'date' => $date,
                                'status' => null,
                                'deleted_at' => null,
                                'userattendancelist' => [
                                    $adjustment,
                                ],
                            ];
                        } else {
                            $dateArray[]['day'] = $date;
                        }
                        // }

                        // if ($dateObj->format('Y-m-d') < Carbon::today()->format('Y-m-d') && !empty($scheduled[$key]) && $is_flexible != 1) {

                        //     $sche_from = Carbon::parse($scheduled[$key]['schedule_from']);
                        //     $sche_to = Carbon::parse($scheduled[$key]['schedule_to']);
                        //     $seconds = $sche_from->diffInSeconds($sche_to);

                        //     $absent_time = $this->hoursformat($seconds);
                        //     $dateArray[]['day'] = [
                        //         'date' => $date,
                        //         'userattendancelist' => [
                        //             "attendance_type" =>  "absent",
                        //             "attendance_time" =>  $absent_time
                        //         ],
                        //     ];
                        // }
                        // elseif(empty($scheduled)){
                        //     $dateArray[]['day'] = $date;
                        // }
                    }
                }

                return response()->json([
                    'ApiName' => 'timesheets',
                    'status' => true,
                    'message' => 'Success',
                    'pay_frequency' => $payFrequency,
                    'data' => $dateArray,
                ], 200);
            }
        }

        return response()->json(['error' => 'Position pay frequency not found'], 400);
    }

    private function mapAttendanceType($type)
    {
        $mapping = [
            'clock in' => 'present',
            'break' => 'break',
            'end break' => 'present',
            'lunch' => 'lunch',
            'end lunch' => 'present',
            'clock out' => 'clock out',
            'Adjustment' => 'Adjustment',
        ];

        return $mapping[$type] ?? 'unknown';
    }

    protected function evereeattendancepush($data)
    {
        $user_id = Auth::user()->id;
        $val = [];
        $checkedin = UserAttendance::where('user_id', $user_id)->whereDate('date', $data['time'])->first();

        if ($checkedin) {
            $timeslists = UserAttendanceDetail::where('user_attendance_id', $checkedin->id)->get();
            $createBreaks = [];
            $i = 0;
            $everee_workerId = Auth::user()->everee_workerId; // '98a66eb0-d379-41cd-81e4-76e1807d3caf';//
            $employee_id = Auth::user()->employee_id; // 'SEQ0045';//
            $dataobj = new \stdClass;
            $dataobj->id = $user_id;
            $dataobj->employee_id = $employee_id;
            $getEverreUserResponse = $this->getEvreeUserinformation($dataobj, $everee_workerId);
            $workLocationId = isset($getEverreUserResponse['position']['current']['id']) ? $getEverreUserResponse['position']['current']['id'] : '';

            if ($workLocationId != '') {
                foreach ($timeslists as $index => $timeslist) {
                    if ($timeslist->type == 'clock in') {
                        $val[$i]['shiftStartEpochSeconds'] = strtotime($timeslist->attendance_date);
                    } elseif ($timeslist->type == 'break') {
                        $createBreaks['breakStartEpochSeconds'] = strtotime($timeslist->attendance_date);
                        // $endtime = $timeslists[$index + 1]->attendance_date;
                        $createBreaks['breakEndEpochSeconds'] = strtotime($timeslists[$index + 1]->attendance_date);
                        // $val[$i]['createBreaks'] = $createBreaks;
                    } elseif ($timeslist->type == 'lunch') {
                        $val[$i]['shiftEndEpochSeconds'] = strtotime($timeslist->attendance_date);
                        $i++;
                    } elseif ($timeslist->type == 'end lunch') {
                        $val[$i]['shiftStartEpochSeconds'] = strtotime($timeslist->attendance_date);
                    } elseif ($timeslist->type == 'clock out') {
                        $val[$i]['shiftEndEpochSeconds'] = strtotime($timeslist->attendance_date);
                    }
                }

                foreach ($val as $va) {
                    $va['workerId'] = $everee_workerId;
                    $va['externalWorkerId'] = $employee_id;
                    $va['workLocationId'] = $workLocationId;
                    // print_r($va);
                    $res = $this->shiftadd($va, $user_id);
                    if ($res) {
                        $response = json_decode($res);
                        if (isset($response->workedShiftId)) {
                            $currentshifid = $checkedin->everee_shift_ids;
                            $workedShiftId = $currentshifid.','.$response->workedShiftId;
                            $pdata['everee_shift_ids'] = trim($workedShiftId, ',');
                            $checkedin->update($pdata);
                            $checkedin->refresh();
                        }
                    }
                }
            }
        }
    }

    public function payroll_wages_create($data)
    {

        // $data = [
        //     "id" => 5,
        //     "user_id" => 23,
        //     "current_time" => "07:32:05",
        //     "lunch_time" => "0",
        //     "break_time" => "0",
        //     "date" => "2024-07-18"
        // ];

        $attendaneId = $data['id'];
        $userId = $data['user_id'];
        $totalHours = $data['current_time'];
        $date = $data['date'];
        $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'desc')->first();
        if ($userWagesHistory) {
            // $subPositionId = Auth::user()->sub_position_id;
            // $stop_payroll = Auth::user()->stop_payroll;

            $user = User::find($userId);
            $subPositionId = $user->sub_position_id;
            $stop_payroll = $user->stop_payroll;

            // $payFrequency = $this->payFrequencyNew($date, $subPositionId);
            $payFrequency = $this->payFrequency($date, $subPositionId, $userId);
            if ($payFrequency && $payFrequency->closed_status == 0) {

                $overtimeRate = $userWagesHistory->overtime_rate;
                $sDate = $payFrequency->pay_period_from;
                $eDate = $payFrequency->pay_period_to;

                if ($userWagesHistory->pay_type == 'Salary') {

                    // $year = 2023;
                    // $month = 7; // July
                    // $daysInMonth = getDaysInMonth($year, $month);

                    $payrollHourlySalary = PayrollHourlySalary::where(['user_id' => $userId, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->where('status', '!=', 3)->first();

                    $dataArray = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'date' => $date,
                        'salary' => $userWagesHistory->pay_rate,
                        'total' => $userWagesHistory->pay_rate,
                        'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                        'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                        'is_stop_payroll' => $stop_payroll,
                        'status' => 1,
                    ];

                    if ($payrollHourlySalary) {
                        $payrollHourlySalary->update($dataArray);
                    } else {
                        PayrollHourlySalary::create($dataArray);
                        $this->updateCommission($userId, $subPositionId, '0', $date);
                    }

                    // $this->payrollOvertimeForSalary($userId, $subPositionId, $sDate, $eDate, $overtimeRate, $date, $stop_payroll);

                } else {

                    $timeString = $data['current_time'];
                    [$hours, $minutes, $seconds] = explode(':', $timeString);
                    $totalMinutes = floor(($hours * 60) + $minutes + ($seconds / 60));
                    if ($totalMinutes > 0) {
                        // $totalHours = floor($totalMinutes / 60);
                        $totalHours = number_format(($totalMinutes / 60), 2);
                        if ($totalHours > 8) {
                            $hourTotal = 8;
                            $formattedTime = '08:00';
                        } else {
                            $hourTotal = $totalHours;
                            $dateTime = new DateTime($timeString);
                            $formattedTime = $dateTime->format('H:i');
                        }
                        $totalRate = ($hourTotal * $userWagesHistory->pay_rate);

                    }

                    $payrollHourlySalary = PayrollHourlySalary::where(['user_id' => $userId, 'date' => $date, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->where('status', '!=', 3)->first();

                    $dataArray = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'date' => $date,
                        'hourly_rate' => $userWagesHistory->pay_rate,
                        'regular_hours' => isset($formattedTime) ? $formattedTime : 0,
                        'total' => isset($totalRate) ? $totalRate : 0,
                        'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                        'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                        'is_stop_payroll' => $stop_payroll,
                        'status' => 1,
                    ];

                    if ($payrollHourlySalary) {
                        $payrollHourlySalary->update($dataArray);
                    } else {
                        PayrollHourlySalary::create($dataArray);
                        $this->updateCommission($userId, $subPositionId, '0', $date);
                    }

                    if ($totalHours > 8) {
                        $this->payrollOvertimeForHourly($userId, $subPositionId, $sDate, $eDate, $overtimeRate, $date, $stop_payroll, $totalHours);
                    }
                }
            }

        }

    }

    protected function payrollOvertimeForSalary($userId, $subPositionId, $sDate, $eDate, $overtimeRate, $date, $stop_payroll)
    {

        $positionPayFrequency = PositionPayFrequency::select('position_id', 'frequency_type_id')->where(['position_id' => $subPositionId])->first();
        $totalLogin = 0;
        if ($positionPayFrequency) {
            if ($positionPayFrequency->frequency_type_id == 2) {
                $totalLogin = 40;
            } elseif ($positionPayFrequency->frequency_type_id == 5) {
                $totalLogin = 40 * 4;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                $totalLogin = 40;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                $totalLogin = 40;
            }
        }

        // $sDate = '2024-06-26';
        // $eDate = '2024-07-03';

        // $userAttendance = UserAttendance::where(['user_id'=> $userId])->whereBetween('date', [$sDate, $eDate])->sum(DB::raw("TIME_TO_SEC(current_time)"));
        $userAttendance = UserAttendance::where(['user_id' => $userId])->whereBetween('date', [$sDate, $eDate])->get();
        $totalSeconds = 0;
        $totalHours = 0;
        if (count($userAttendance) > 0) {
            foreach ($userAttendance as $key => $value) {
                $timeA = Carbon::createFromFormat('H:i:s', $value['current_time']);
                $secondsA = $timeA->hour * 3600 + $timeA->minute * 60 + $timeA->second;

                $totalSeconds = $totalSeconds + $secondsA;
            }
        }

        if ($totalSeconds) {
            $totaltime = $this->hoursformat($totalSeconds);
            [$hours, $minutes, $seconds] = explode(':', $totaltime);
            $totalMinutes = floor(($hours * 60) + $minutes + ($seconds / 60));
            $totalHours = number_format(($totalMinutes / 60), 2);
        }

        if ($totalHours > $totalLogin) {
            $overtime = ($totalHours - $totalLogin);
            $totals = (($totalHours - $totalLogin) * $overtimeRate);
            $payrollOvertime = PayrollOvertime::where(['user_id' => $userId, 'pay_period_from' => $sDate, 'pay_period_to' => $eDate])->where('status', '!=', 3)->first();
            $dataArray = [
                'user_id' => $userId,
                'position_id' => $subPositionId,
                'date' => $date,
                'overtime_rate' => $overtimeRate,
                'overtime' => $overtime,
                'total' => $totals,
                'pay_period_from' => isset($sDate) ? $sDate : null,
                'pay_period_to' => isset($eDate) ? $eDate : null,
                'is_stop_payroll' => $stop_payroll,
                'status' => 1,
            ];

            if ($payrollOvertime) {
                $payrollOvertime->update($dataArray);
            } else {
                PayrollOvertime::create($dataArray);
                $this->updateCommission($userId, $subPositionId, '0', $date);
            }

        }

    }

    protected function payrollOvertimeForHourly($userId, $subPositionId, $sDate, $eDate, $overtimeRate, $date, $stop_payroll, $totalHours)
    {

        if ($totalHours > 8) {
            $overtime = ($totalHours - 8);
            $totals = (($totalHours - 8) * $overtimeRate);
            $payrollOvertime = PayrollOvertime::where(['user_id' => $userId, 'date' => $date, 'pay_period_from' => $sDate, 'pay_period_to' => $eDate])->where('status', '!=', 3)->first();
            $dataArray = [
                'user_id' => $userId,
                'position_id' => $subPositionId,
                'date' => $date,
                'overtime_rate' => $overtimeRate,
                'overtime' => $overtime,
                'total' => $totals,
                'pay_period_from' => isset($sDate) ? $sDate : null,
                'pay_period_to' => isset($eDate) ? $eDate : null,
                'is_stop_payroll' => $stop_payroll,
                'status' => 1,
            ];

            if ($payrollOvertime) {
                $payrollOvertime->update($dataArray);
            } else {
                PayrollOvertime::create($dataArray);
                $this->updateCommission($userId, $subPositionId, '0', $date);
            }

        }

    }

    public function getDaysInMonth($year, $month)
    {
        $date = Carbon::create($year, $month, 1);

        return $date->daysInMonth;
    }

    public function getDaysInDateRange($startDate, $endDate)
    {
        $days = [];
        $currentDay = $startDate->copy();
        while ($currentDay->lte($endDate)) {
            $days[] = $currentDay->toDateString();
            $currentDay->addDay();
        }

        return $days;
    }

    public function mySchedule(Request $request)
    {
        $time_format = ($request->time_format == '12') ? 'h:i:s A' : 'H:i:s';
        $today = Carbon::today();
        $user_id = Auth::id();
        $user = User::find($user_id);
        if ($request->section == 'your_leaves') {
            $section_date = Carbon::parse($request->section_date);
            $startOfCurrentWeek = $section_date->startOfMonth();
            $endOfNextWeek = $startOfCurrentWeek->copy()->addWeeks(1)->endOfMonth();
        } else {
            $startOfCurrentWeek = $request->start_date ? Carbon::parse($request->start_date) : $today->startOfWeek(Carbon::MONDAY);
            $endOfNextWeek = $request->end_date ? Carbon::parse($request->end_date) : $startOfCurrentWeek->copy()->addWeeks(1)->endOfWeek(Carbon::SUNDAY);
        }

        // Ensure end date is not before start date
        if ($endOfNextWeek->lessThan($startOfCurrentWeek)) {
            return response()->json([
                'ApiName' => 'error',
                'status' => false,
                'message' => 'End date cannot be before start date.',
            ], 400);
        }

        $days = $this->getDaysInDateRange($startOfCurrentWeek, $endOfNextWeek);
        $data = [];
        $weekly_totals = [];

        // Initialize counters
        $present_count = 0;
        $absent_count = 0;
        $late_count = 0;
        $total_unpaid_leave = 0;
        $early_time_out_remaider = [];
        $PTOs = [
            'total_ptos' => null,
            'total_user_ptos' => null,
            'total_remaining_ptos' => null,
        ];

        if (isset($request->section) && isset($request->section_date) && $request->section == 'your_leaves' && $request->section_date != null) {

            $PTOs = $this->calculatePTOs($user_id, $request->section_date);
        }

        // Eager load related models
        $userSchedule = UserSchedule::with('userSchedulelist')->where('user_id', $user_id)->first();
        $ptoandLeaves = ApprovalsAndRequest::where('user_id', $user_id)
            ->whereIn('adjustment_type_id', [7, 8])
            ->where('status', 'Approved')
            ->whereBetween('start_date', [$startOfCurrentWeek, $endOfNextWeek])
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->start_date)->format('Y-m-d');
            });

        $userAttendance = UserAttendance::with('userattendancelist')
            ->where('user_id', $user_id)
            ->whereBetween('date', [$startOfCurrentWeek, $endOfNextWeek])
            ->get()
            ->keyBy('date');

        // Initialize weekly totals
        $current_week_start = $startOfCurrentWeek->copy();
        while ($current_week_start->lte($endOfNextWeek)) {
            $weekly_totals[$current_week_start->format('W-Y')] = [
                'scheduled_hours' => 0,
                'worked_hours' => 0,
                'OT' => 0,
            ];
            $current_week_start->addWeek();
        }
        $usedPtos = 0;
        foreach ($days as $key => $day) {
            $date = Carbon::parse($day);
            $data[$key] = [
                'present_type' => null,
                'present_from' => null,
                'present_time' => null,
                'present_to' => null,
                'leave_type' => null,
                'pto_hours' => null,
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('l'),
                'schedule' => [],
            ];

            $data[$key]['user_id'] = $user_id;
            $data[$key]['userData']['first_name'] = $user->first_name;
            $data[$key]['userData']['middle_name'] = $user->middle_name;
            $data[$key]['userData']['last_name'] = $user->last_name;

            // Leaves and PTO
            // if (isset($ptoandLeaves[$day])) {
            //     foreach ($ptoandLeaves[$day] as $value) {
            //         if ($value->adjustment_type_id == 7) {
            //             $data[$key]['leave_type'] = 'Leave (Unpaid)';
            //             $total_unpaid_leave ++;
            //         } elseif ($value->adjustment_type_id == 8) {
            //             $data[$key]['leave_type'] = 'PTO';
            //             $data[$key]['pto_hours'] = $value->pto_hours_perday;
            //         }
            //     }
            // }

            $req_approvals_pto = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();
            if (! empty($req_approvals_pto)) {
                $data[$key]['leave_type'] = 'PTO';
                $data[$key]['pto_hours'] = $req_approvals_pto->pto_hours_perday;
                $usedPtos += $req_approvals_pto->pto_hours_perday;
            }
            // print_r($req_approvals_pto);echo "\n";
            // echo $data[$key]['leave_type']."\n";
            $req_approvals_leave = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->where('adjustment_type_id', 7)
                ->where('status', 'Approved')
                ->first();

            if (! empty($req_approvals_leave)) {
                $total_unpaid_leave++;
                $data[$key]['leave_type'] = 'Leave (Unpaid)';
            }

            // Schedule
            if ($userSchedule) {
                foreach ($userSchedule->userSchedulelist as $schedule) {
                    // echo '<pre>'; print_r($userSchedule->userSchedulelist);
                    $day_name = $this->getDayName($schedule->work_days);
                    $schedule_from = Carbon::parse($schedule->schedule_from);
                    $schedule_to = Carbon::parse($schedule->schedule_to);

                    if ($schedule_from->format('Y-m-d') == $date->format('Y-m-d')) {
                        $data[$key]['schedule'][] = [
                            'schedule_from' => $schedule_from->format($time_format) == '00:00:00' ? null : $schedule_from->format($time_format),
                            'schedule_to' => $schedule_to->format($time_format) == '00:00:00' ? null : $schedule_to->format($time_format),
                            'is_flexible' => $schedule->is_flexible ? $schedule->is_flexible : 0,
                        ];
                    }

                    // if ($schedule->repeated_batch == 1 && $day_name == $date->format('l')) {
                    //     $data[$key]['schedule'][] = [
                    //         'schedule_from' => $schedule_from->format($time_format) == '00:00:00' ? null : $schedule_from->format($time_format),
                    //         'schedule_to' => $schedule_to->format($time_format) == '00:00:00' ? null : $schedule_to->format($time_format)
                    //     ];
                    // } elseif ($schedule_from->format('Y-m-d') == $date->format('Y-m-d') && $schedule->repeated_batch == 0) {
                    //     $data[$key]['schedule'][] = [
                    //         'schedule_from' => $schedule_from->format($time_format) == '00:00:00' ? null : $schedule_from->format($time_format),
                    //         'schedule_to' => $schedule_to->format($time_format) == '00:00:00' ? null : $schedule_to->format($time_format)
                    //     ];
                    // }
                }
            }

            // Attendance
            if (isset($userAttendance[$day])) {
                $attendance = $userAttendance[$day];
                $clockInTime = null;
                $clockOutTime = null;
                $worked_time = 0;
                $scheduled_time = 0;

                foreach ($attendance->userattendancelist as $record) {
                    // return $record;
                    if ($record['adjustment_id'] > 0) {
                        $adjustment_date = Carbon::parse($record['attendance_date'])->toDateString();
                        $req_approvals_data = ApprovalsAndRequest::where('user_id', $user_id)
                            ->where('adjustment_date', '=', $adjustment_date)
                            // ->where('adjustment_date','=',date('Y-m-d'))
                            ->where('adjustment_type_id', 9)
                            ->where('status', 'Approved')
                            ->first();
                        if (! empty($req_approvals_data)) {
                            $data[$key]['present_type'] = 'present';
                            $data[$key]['present_from'] = isset($req_approvals_data->clock_in) ? $req_approvals_data->clock_in : null;
                            $data[$key]['present_to'] = isset($req_approvals_data->clock_out) ? $req_approvals_data->clock_out : null;
                        }
                    } else {
                        if ($record['type'] == 'clock in') {
                            $clockInTime = Carbon::parse($record['attendance_date']);
                            $data[$key]['present_type'] = 'present';
                            $data[$key]['present_from'] = $clockInTime->format($time_format);
                        } elseif ($record['type'] == 'clock out') {
                            $clockOutTime = Carbon::parse($record['attendance_date']);
                            $data[$key]['present_to'] = $clockOutTime->format($time_format);
                        }
                    }

                }

                if ($clockInTime && $clockOutTime) {
                    $interval = $clockInTime->diff($clockOutTime);
                    $data[$key]['present_time'] = $interval->format('%h:%i:%s');
                    // Calculate worked hours for the week
                    $worked_hours = $clockInTime->diffInHours($clockOutTime);
                    $worked_time = $clockInTime->diffInSeconds($clockOutTime);
                    $week_key = $clockInTime->format('W-Y');
                    if (isset($weekly_totals[$week_key])) {
                        $weekly_totals[$week_key]['worked_hours'] += $worked_hours;
                    }
                }

                // Check if late

                foreach ($data[$key]['schedule'] as $schedule) {
                    $schedule_from = Carbon::parse($schedule['schedule_from']);
                    $schedule_to = Carbon::parse($schedule['schedule_to']);
                    $scheduled_time += $schedule_from->diffInSeconds($schedule_to);
                    if ($data[$key]['present_from'] && $schedule['schedule_from'] && Carbon::parse($data[$key]['present_from'])->gt(Carbon::parse($schedule['schedule_from']))) {
                        $data[$key]['present_type'] = 'late';
                        $late_count++;
                    }
                }
                if (($scheduled_time - $worked_time) > 0) {
                    $early_time_out_remaider[] = [
                        'date' => $date->format('Y-m-d'),
                        'day' => $date->format('l'),
                        'time' => $this->hoursformat($scheduled_time - $worked_time)];
                }

                $present_count++;
            } else {
                $scheduled_time = 0;
                $is_flexible = isset($data[$key]['schedule']['0']['is_flexible']) ? $data[$key]['schedule']['0']['is_flexible'] : false;
                $schedule_from = isset($data[$key]['schedule']['0']['schedule_from']) ? $data[$key]['schedule']['0']['schedule_from'] : null;
                $schedule_to = isset($data[$key]['schedule']['0']['schedule_to']) ? $data[$key]['schedule']['0']['schedule_to'] : null;
                // foreach ($data[$key]['schedule'] as $schedule) {
                //     $schedule_from = Carbon::parse($schedule['schedule_from']);
                //     $schedule_to = Carbon::parse($schedule['schedule_to'] );
                // dump($schedule_from,$schedule_to);

                $req_approvals_data = ApprovalsAndRequest::where('user_id', $user_id)
                    ->where('adjustment_date', '=', $data[$key]['date'])
                // ->where('adjustment_date','=',date('Y-m-d'))
                    ->where('adjustment_type_id', 9)
                    ->where('status', 'Approved')
                    ->first();

                if ($date->format('Y-m-d') < Carbon::today()->format('Y-m-d') && $schedule_from != null && $schedule_to != null && $data[$key]['present_from'] == null && ! $req_approvals_data) {
                    $data[$key]['present_type'] = 'Absent';
                    $absent_count++;
                } elseif ($req_approvals_data) {
                    $user_checkin = isset($req_approvals_data) ? $req_approvals_data->clock_in : null;
                    $user_checkout = isset($req_approvals_data) ? $req_approvals_data->clock_out : null;
                    $data[$key]['present_type'] = 'present';
                    $data[$key]['present_from'] = $user_checkin;
                    $data[$key]['present_to'] = $user_checkout;
                    $present_count++;
                }

                // $scheduled_time += $schedule_from->diffInSeconds($schedule_to);
                // if ($data[$key]['present_from'] && $schedule['schedule_from'] && Carbon::parse($data[$key]['present_from'])->gt(Carbon::parse($schedule['schedule_from']))) {
                //     $data[$key]['present_type'] = 'late';
                // $late_count++;
                // }
                // }
                // if ($date->format('Y-m-d') < Carbon::today()->format('Y-m-d') && !empty($data[$key]['schedule']) && $data[$key]['present_from'] == null && $is_flexible != 1) {
                //     $data[$key]['present_type'] = 'Absent';
                //     $absent_count++;
                // }
            }
            // // check for is_availale
            // foreach ($data[$key]['schedule'] as $schedule) {
            //     if (is_null($data[$key]['present_from']) && ( ( $schedule['schedule_from'] == '12:00:00 AM' ) || ( is_null($schedule['schedule_from']) && is_null($schedule['schedule_from']) ) ) ){
            //         $data[$key]['present_type'] = null;
            //     }
            // }

            // Update weekly totals
            $week_key = $date->format('W-Y');
            if (isset($weekly_totals[$week_key])) {
                $weekly_totals[$week_key]['scheduled_hours'] += $data[$key]['schedule'] ? $this->calculateScheduledHours($data[$key]['schedule'], $time_format) : 0;
                $weekly_totals[$week_key]['OT'] = ($weekly_totals[$week_key]['worked_hours'] - $weekly_totals[$week_key]['scheduled_hours']) > 0 ? ($weekly_totals[$week_key]['worked_hours'] - $weekly_totals[$week_key]['scheduled_hours']) : 0;
            }
        }

        // Convert weekly_totals to a zero-indexed array for response
        $weekly_totals = array_values($weekly_totals);

        return response()->json([
            'ApiName' => 'my_schedules',
            'status' => true,
            'message' => '',
            'data' => $data,
            'weekly_totals' => $weekly_totals,
            'early_time_out_remaider' => $early_time_out_remaider,
            'your_leaves' => [
                'unpaid_leave_taken' => $total_unpaid_leave,
                'total_ptos' => $PTOs['total_ptos'],
                // 'total_user_ptos' => $PTOs['total_user_ptos'],
                'total_user_ptos' => $usedPtos,
                'total_remaining_ptos' => $PTOs['total_remaining_ptos'],
            ],
            'counts' => [
                'present' => $present_count,
                'absent' => $absent_count,
                'late' => $late_count,
            ],
        ], 200);
    }

    private function calculateScheduledHours($schedule, $time_format)
    {
        $total_hours = 0;
        foreach ($schedule as $shift) {
            try {
                $from = Carbon::createFromFormat($time_format, $shift['schedule_from']);
                $to = Carbon::createFromFormat($time_format, $shift['schedule_to']);
                $total_hours += $from->diffInHours($to);
            } catch (\Exception $e) {
                // Log the error and continue
                // \Log::error("Error parsing time: " . $e->getMessage() . " | From: " . $shift['schedule_from'] . " To: " . $shift['schedule_to']);
            }
        }

        return $total_hours;
    }

    public function getDayName($dayNumber)
    {
        $daysOfWeek = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        return $daysOfWeek[$dayNumber] ?? 'Invalid day number';
    }

    public function your_earnings(Request $request)
    {
        // $validatedData = $request->validate([
        //     'start_date' => 'required|date|before:end_date',
        //     'end_date' => 'required|date|after:start_date',
        // ]);

        $time_format = ($request->time_format == '24') ? 'H:i:s' : 'h:i:s A';

        $user_id = Auth::id();
        $start_date = $request->start_date ? Carbon::parse($request->start_date) : null;
        $end_date = $request->end_date ? Carbon::parse($request->end_date) : null;
        $user = User::find($user_id);
        $positionId = $user->sub_position_id ? $user->sub_position_id : $user->position_id;

        $positionPayFrequency = PositionPayFrequency::query()->where('position_id', $positionId)->first();
        if ($positionPayFrequency) {
            $type = '';
            if ($positionPayFrequency->frequency_type_id == FrequencyType::WEEKLY_ID) {
                $class = WeeklyPayFrequency::class;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::MONTHLY_ID) {
                $class = MonthlyPayFrequency::class;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
            }

            if (isset($class)) {
                $payFrequencyQuery = $class::query();

                if ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID || $positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                    $payFrequency = $payFrequencyQuery->where('type', $type)->whereRaw('"'.date('Y-m-d').'" between `pay_period_from` and `pay_period_to`')->first();
                } else {
                    $payFrequency = $payFrequencyQuery->whereRaw('"'.date('Y-m-d').'" between `pay_period_from` and `pay_period_to`')->first();
                }

                if (! $payFrequency) {

                    return response()->json([
                        'ApiName' => 'your_earnings',
                        'status' => false,
                        'message' => 'Pay period not found',
                        'data' => [],
                    ], 400);
                }

                $start_date = Carbon::parse($payFrequency->pay_period_from);
                $end_date = Carbon::parse($payFrequency->pay_period_to);
                $days = $this->getDaysInDateRange($start_date, $end_date);

                $userSchedule = UserSchedule::with('userSchedulelist')->where('user_id', $user_id)->first();

                $userAttendance = UserAttendance::where('user_id', $user_id)
                    ->whereBetween('date', [$start_date, $end_date])
                    ->get()
                    ->keyBy('date');
                $approvalsAndRequest = ApprovalsAndRequest::where('user_id', $user_id)
                    ->whereBetween('adjustment_date', [$start_date, $end_date])
                    ->where('adjustment_date', '<=', date('Y-m-d'))
                    ->where('adjustment_type_id', 9)
                    ->where('status', 'Approved')
                    ->get()
                    ->keyBy('adjustment_date');

                $data = [];
                $scheduled_hours = 0;
                $scheduled_lunch_time = 0;
                $total_present_hours = 0;
                foreach ($days as $key => $day) {
                    $date = Carbon::parse($day);
                    $data[$key] = [
                        'schedule' => [],
                    ];
                    if ($userSchedule) {
                        foreach ($userSchedule->userSchedulelist as $schedule) {
                            $day_name = $this->getDayName($schedule->work_days);
                            $schedule_from = Carbon::parse($schedule->schedule_from);
                            $schedule_to = Carbon::parse($schedule->schedule_to);
                            if ($schedule_from->format('Y-m-d') == $date->format('Y-m-d')) {
                                $lunch_duration = isset($schedule->lunch_duration) ? (int) preg_replace('/\D/', '', $schedule->lunch_duration) : 0;
                                $scheduled_lunch_time += is_numeric($lunch_duration) ? $lunch_duration * 60 : 0;
                                $data[$key]['schedule'][] = [
                                    'schedule_from' => $schedule_from->format($time_format) == '00:00:00' ? null : $schedule_from->format($time_format),
                                    'schedule_to' => $schedule_to->format($time_format) == '00:00:00' ? null : $schedule_to->format($time_format),
                                    'is_flexible' => $schedule->is_flexible ? $schedule->is_flexible : 0,
                                ];
                            }
                        }
                        $scheduled_time = $data[$key]['schedule'] ? $data[$key]['schedule'] : 0;
                        if (! empty($data[$key]['schedule'])) {
                            foreach ($scheduled_time as $hours) {
                                $schedule_from = Carbon::parse($hours['schedule_from']);
                                $schedule_to = Carbon::parse($hours['schedule_to']);
                                $scheduled_hours += $schedule_from->diffInSeconds($schedule_to);
                            }
                        }
                    }

                    if (isset($userAttendance[$day]) && ! empty($userAttendance[$day]->current_time)) {
                        $attendance = $userAttendance[$day];
                        // return $attendance;
                        $adjustment_date = Carbon::parse($attendance->date)->toDateString();
                        $user_attendance_details = UserAttendanceDetail::where('user_attendance_id', $attendance->id)
                            ->whereDate('attendance_date', $adjustment_date)
                            ->where('adjustment_id', '>', 0)
                            ->first();
                        if ($user_attendance_details) {
                            // calculate total clockIn clockOut in seconds
                            $get_request = ApprovalsAndRequest::find($user_attendance_details->adjustment_id);
                            $user_checkin = isset($get_request) ? $get_request->clock_in : $adjustment_date.' 00:00:00';
                            $user_checkout = isset($get_request) ? $get_request->clock_out : $adjustment_date.' 00:00:00';
                            $clock_in = Carbon::parse($user_checkin);
                            $clock_out = Carbon::parse($user_checkout);
                            $total_breack = (($get_request->lunch_adjustment + $get_request->break_adjustment) * 60);
                            $total_presennt = $clock_in->diffInSeconds($clock_out);
                            $total_present_hours += $total_presennt - $total_breack;

                        } else {
                            $total_present_seconds = $attendance->current_time;
                            [$hours, $minutes, $seconds] = explode(':', $total_present_seconds);
                            $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
                            $total_present_hours += $totalSeconds;
                        }

                    } else {
                        if (isset($approvalsAndRequest[$day])) {
                            $adjustment = $approvalsAndRequest[$day];
                            $clock_in = Carbon::parse($adjustment->clock_in);
                            $clock_out = Carbon::parse($adjustment->clock_out);
                            $total_breack = (($adjustment->lunch_adjustment + $adjustment->break_adjustment) * 60);
                            $total_presennt = $clock_in->diffInSeconds($clock_out);
                            $total_present_hours += $total_presennt - $total_breack;
                        }
                    }

                    // if(isset($approvalsAndRequest[$day])) {
                    //     $adjustment = $approvalsAndRequest[$day];
                    //     $clock_in = Carbon::parse($adjustment->clock_in);
                    //     $clock_out = Carbon::parse($adjustment->clock_out );
                    //     $total_breack = (($adjustment->lunch_adjustment + $adjustment->break_adjustment) * 60);
                    //     $total_presennt = $clock_in->diffInSeconds($clock_out);
                    //     $total_present_hours += $total_presennt - $total_breack;
                    // }
                }
                $scheduled_hours = $scheduled_hours - $scheduled_lunch_time;
                $ot_hours = ($total_present_hours - $scheduled_hours) > 0 ? $total_present_hours - $scheduled_hours : 0;

                $regular_hours = $total_present_hours - $ot_hours;
                $regular_hours = $this->hoursformat($regular_hours);
                $ot_hours = $this->hoursformat($ot_hours);
                $total_present_hours = $this->hoursformat($total_present_hours);
                $scheduled_hours = $this->hoursformat($scheduled_hours);

                $ot_salery = $this->calculateSalary($ot_hours, $user->overtime_rate);
                $regular_salary = $this->calculateSalary($regular_hours, $user->pay_rate);
                $estimated_earning = $ot_salery + $regular_salary;
                $data = ['scheduled_hours' => $scheduled_hours, 'worked_hours' => $total_present_hours, 'regular_hours' => $regular_hours, 'ot_hours' => $ot_hours, 'estimated_earning' => $estimated_earning, 'pay_frequency' => $payFrequency];

                return response()->json([
                    'ApiName' => 'your_earnings',
                    'status' => true,
                    'message' => 'success',
                    'data' => $data,
                ], 200);
            }
        }

        return response()->json([
            'ApiName' => 'your_earnings',
            'status' => false,
            'message' => 'Position pay frequency not found',
            'data' => [],
        ], 400);
    }

    public function calculateSalary($loggedTime, $hourlyWage)
    {
        // Split the logged time into hours, minutes, and seconds
        [$hours, $minutes, $seconds] = explode(':', $loggedTime);

        // Convert everything to hours
        $totalHours = $hours + ($minutes / 60) + ($seconds / 3600);

        // Calculate the salary
        $salary = $totalHours * $hourlyWage;

        return $salary;
    }

    private function calculatePTOs($user_id = null, $date = null)
    {
        if ($date == null) {
            $date = date('Y-m-d');
        }
        if ($user_id == null) {
            $user_id = Auth::user()->id;
        }

        $user = User::find($user_id);
        $total_used_pto_hours = 0;
        $total_pto_hours = 0;
        $date = Carbon::parse($date);

        if ($user->unused_pto_expires == 'Monthly' || $user->unused_pto_expires == 'Expires Monthly') {
            $total_pto_hours = $user->pto_hours;
            $start_date = $date->copy()->startOfMonth()->toDateString();
            $end_date = $date->copy()->endOfMonth()->toDateString();
            $user_ptos = ApprovalsAndRequest::where('user_id', $user->id)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->where(function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('start_date', [$start_date, $end_date])
                        ->orWhereBetween('end_date', [$start_date, $end_date]);
                })
                ->orderBy('start_date', 'ASC')->get(['start_date', 'end_date', 'pto_hours_perday']);
            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                if ($pto_end_date->lt($start_date) || $pto_start_date->gt($end_date)) {
                    continue; // Skip PTOs outside the current month
                }

                $overlap_start = $pto_start_date->gt($start_date) ? $pto_start_date : $start_date;
                $overlap_end = $pto_end_date->lt($end_date) ? $pto_end_date : $end_date;
                $days = $overlap_start->diffInDays($overlap_end) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        } elseif ($user->unused_pto_expires == 'Annually' || $user->unused_pto_expires == 'Expires Annually') {
            $start_date = $date->copy()->startOfYear()->toDateString();
            $end_date = $date->copy()->endOfYear()->toDateString();

            $pto_start_date = Carbon::parse($user->created_at)->lt($date->copy()->startOfYear()) ? $date->copy()->startOfYear() : Carbon::parse($user->created_at);
            $monthCount = $pto_start_date->diffInMonths($date);
            $total_pto_hours = $user->pto_hours * ($monthCount + 1);
            $user_ptos = ApprovalsAndRequest::where('user_id', $user->id)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->where(function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('start_date', [$start_date, $end_date])
                        ->orWhereBetween('end_date', [$start_date, $end_date]);
                })
                ->orderBy('start_date', 'ASC')->get(['start_date', 'end_date', 'pto_hours_perday']);

            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                if ($pto_end_date->lt($start_date) || $pto_start_date->gt($end_date)) {
                    continue; // Skip PTOs outside the current month
                }
                $overlap_start = $pto_start_date->gt($start_date) ? $pto_start_date : $start_date;
                $overlap_end = $pto_end_date->lt($end_date) ? $pto_end_date : $end_date;
                $days = $overlap_start->diffInDays($overlap_end) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        } elseif ($user->unused_pto_expires == 'Accrues Continuously' || $user->unused_pto_expires == 'Expires Accrues Continuously') {
            $monthCount = Carbon::parse($user->created_at)->diffInMonths($date);
            $total_pto_hours = $user->pto_hours * ($monthCount + 1);
            $user_ptos = ApprovalsAndRequest::where('user_id', $user->id)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->get(['start_date', 'end_date', 'pto_hours_perday']);

            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                $days = $pto_start_date->diffInDays($pto_end_date) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        }

        return [
            'total_ptos' => (int) $total_pto_hours,
            'total_user_ptos' => (int) $total_used_pto_hours,
            'total_remaining_ptos' => (int) $total_pto_hours - $total_used_pto_hours,
        ];
    }

    public function timeformateupdate(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required',
        ]);
        $type = $request->type ?? '';
        $user_id = $request->user_id ?? Auth::user()->id;
        try {
            if ($user_id && $type) {
                $user = User::find($user_id); // Find user with ID 1
                if ($user) {
                    $user->time_format = $type;
                    $user->save(); // Save the changes to the database
                }
            }

            return response()->json([
                'ApiName' => 'time_formate_update',
                'status' => true,
                'message' => 'Successfully updated.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'time_formate_update',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function calculateHourlyDailyPay($payRate, $type)
    {
        switch ($type) {
            case 'Per Hour':
                return $payRate; // Assuming 8 hours per workday

            case 'Weekly':
                return $payRate / 7; // Assuming 5 workdays per week

            case 'Bi-Weekly':
                return $payRate / 14; // Assuming 10 workdays per bi-weekly period

            case 'Monthly':
                return $payRate / 30; // Assuming 20 workdays per month

            case 'Semi-Monthly':
                return $payRate / 15; // Assuming 24 workdays per semi-monthly period

            default:
                return 0;
        }
    }

    private function calculateSalaryDailyPay($payRate, $type)
    {
        switch ($type) {
            case 'Per Hour':
                return ($payRate / 2080) * 8; // Assuming 2080 hours/year and 8 hours per workday

            case 'Weekly':
                return $payRate / 7; // Assuming 52 weeks/year and 5 workdays per week

            case 'Bi-Weekly':
                return $payRate / 14; // Assuming 26 bi-weekly periods/year and 10 workdays per period

            case 'Monthly':
                return $payRate / 30; // Assuming 12 months/year and 20 workdays per month

            case 'Semi-Monthly':
                return $payRate / 15; // Assuming 24 semi-monthly periods/year and 2 workdays per period

            default:
                return 0;
        }
    }
}
