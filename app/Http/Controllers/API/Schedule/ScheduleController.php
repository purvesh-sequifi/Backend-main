<?php

namespace App\Http\Controllers\API\Schedule;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ScheduleConfig;
use App\Models\ApprovalsAndRequest;
use App\Models\WpUserSchedule;
use App\Http\Requests\CreateScheduleRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Http\Requests\CreateUserScheduleRequest;
use App\Models\UserSchedule;
use App\Models\UserScheduleDetail;
use  Auth;
use App\Exports\SchedulesExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Positions;
use App\Http\Requests\UpdateUserScheduleFlexibleRequest;
use App\Models\UserAttendance;
use App\Models\UserAttendanceDetail;
use App\Http\Requests\UpdateUserScheduleRequest;
use App\Http\Requests\UpdateUserScheduleRepeatRequest;
use App\Http\Requests\UserAttendenceDetailsRequest;
use App\Models\AdjustementType;
use Validator;
use App\Listeners\UserloginNotificationListener;
use App\Events\UserloginNotification;
use App\Models\ApprovalAndRequestComment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use App\Http\Requests\ApprovedAttendanceStatusRequest;
use App\Core\Traits\EvereeTrait;
use App\Models\UserWagesHistory;
use App\Models\PayrollHourlySalary;
use App\Models\PositionPayFrequency;
use App\Models\PayrollOvertime;
use App\Core\Traits\PayFrequencyTrait;
use App\Core\Traits\PayRollCommissionTrait;
use DateTime;
use App\Models\FrequencyType;
use App\Models\Payroll;
use App\Models\PayrollHistory;
use App\Models\CompanyProfile;
use App\Jobs\GenerateUserSchedulesRepeatJob;
use App\Http\Requests\ClockInClockOutRequest;
use App\Jobs\DeleteUserSchedulesRepeatJob;
use App\Models\PayrollAdjustmentDetail;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    use EvereeTrait,PayFrequencyTrait,PayRollCommissionTrait;

    public function get_scheduling_config()
    {
        $data['clock_format'] = config('constant.scheduling.clock_format');
        $data['lunch_duration'] = config('constant.scheduling.lunch_duration');
        $data['day_no'] = config('constant.scheduling.day_no');
        $check = ScheduleConfig::first();
        if (! empty($check)) {
            $clock_format = array_flip(config('constant.scheduling.clock_format'));
            $ar['clock_format'] = $clock_format[$check['clock_format']];

            $lunch_duration = array_flip(config('constant.scheduling.lunch_duration'));
            $ar['lunch_duration'] = $lunch_duration[$check['default_lunch_dutration']];
            $data['configurationData'] = $ar;
        } else {
            $data['configurationData'] = null;
        }

        // return $data;
        return response()->json([
            'ApiName' => 'get_scheduling_config',
            'status' => true,
            'data' => $data,
        ], 200);
    }

    public function scheduling_config(Request $request): JsonResponse
    {
        $clock_format = config('constant.scheduling.clock_format')[$request->clock_format];
        $lunch_duration = config('constant.scheduling.lunch_duration')[$request->lunch_duration];
        $check = ScheduleConfig::first();
        if (empty($check)) {
            $scheduleObj = new ScheduleConfig;
            $scheduleObj->clock_format = $clock_format;
            $scheduleObj->default_lunch_dutration = $lunch_duration;
            $res = $scheduleObj->save();
        } else {
            $check->clock_format = $clock_format;
            $check->default_lunch_dutration = $lunch_duration;
            $res = $check->save();
        }

        if ($res) {
            return response()->json([
                'ApiName' => 'scheduling_config',
                'status' => true,
                'message' => 'configuration saved',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'scheduling_config',
                'status' => false,
                'message' => 'something went wrong',
            ], 400);
        }
    }

    private function getUserCurrentWeekStartAndEndDates()
    {
        // Get the current date
        $currentDate = Carbon::now();

        // Calculate the start of the current week (Monday)
        $currentWeekStartDate = $currentDate->copy()->startOfWeek(Carbon::MONDAY);

        // Calculate the end of the current week (Sunday)
        $currentWeekEndDate = $currentWeekStartDate->copy()->endOfWeek(Carbon::SUNDAY);

        return [
            'current_week_start_date' => $currentWeekStartDate->toDateString(),
            'current_week_end_date' => $currentWeekEndDate->toDateString(),
        ];
    }

    private function getUserNextDateForDay($day, $work_days, $weekDates)
    {
        // dd($day, $work_days, $weekDates);
        // Find the index of the day name in the week
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $dayIndex = array_search($day, $daysOfWeek);

        if ($dayIndex === false) {
            throw new \InvalidArgumentException("Invalid day name: $day");
        }

        // Calculate the date for the given day name
        $date = Carbon::parse($weekDates['current_week_start_date'])->addDays($dayIndex)->toDateString();

        return $date;

    }

    public function calculateTimeDifference($clockIn, $clockOut)
    {
        // Parse the clock_in and clock_out times using Carbon
        $start = Carbon::parse($clockIn);
        $end = Carbon::parse($clockOut);

        // Calculate the difference
        $differenceInMinutes = $start->diffInMinutes($end);

        // Convert the difference to hours and minutes
        $hours = floor($differenceInMinutes / 60);
        $minutes = $differenceInMinutes % 60;

        // Return the result in a human-readable format
        // return sprintf("%d hours and %d minutes", $hours, $minutes);
        return ['hours' => $hours, 'minutes' => $minutes];
        // return $hours.' '.$minutes;
    }

    public function getCurrentWeekStartDate()
    {
        // Set start and end of the week if necessary
        Carbon::setWeekStartsAt(Carbon::MONDAY); // Optional: Set custom start of the week
        Carbon::setWeekEndsAt(Carbon::SUNDAY); // Optional: Set custom end of the week

        // Get the start date of the next week
        $currentDate = Carbon::now();
        // $currentDate = Carbon::parse("2024-07-02");
        $startOfNextWeek = $currentDate->startOfWeek()->toDateString();

        return $startOfNextWeek;
    }

    private function getMonthStartAndEndDates($date)
    {
        $date = Carbon::parse($date);

        $startOfMonth = $date->copy()->startOfMonth()->toDateString();
        $endOfMonth = $date->copy()->endOfMonth()->toDateString();

        return [
            'start_of_month' => $startOfMonth,
            'end_of_month' => $endOfMonth,
        ];
    }

    public function createUserScheduleOld(CreateUserScheduleRequest $request)
    {
        $userIds = $request->user_id;
        $schedules = $request->schedules;
        $office_id = $request->office_id;
        $is_flexible = $request->is_flexible;
        $is_repeat = $request->is_repeat;
        $gnextWeekStartDate = $this->getCurrentWeekStartDate();
        $nextWeekStartDate = Carbon::parse($gnextWeekStartDate);
        // dd($nextWeekStartDate);

        $currentDate = Carbon::now();
        DB::beginTransaction();
        try {
            $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($userIds as $uid) {
                $existingSchedules = [];
                foreach ($schedules as $schedule) {
                    // $scheduleDate = $schedule['schedule_date'];
                    $scheduleDate = $nextWeekStartDate->copy()->addDays($schedule['work_days'] - 1)->toDateString();
                    // return $scheduleDate;
                    $dayOfWeek = date('l', strtotime($scheduleDate));
                    $dayNumberOfWeek = date('N', strtotime($scheduleDate));
                    // dd($currentDate, $scheduleDate,$dayOfWeek, $dayNumberOfWeek);
                    // Check if the schedule already exists
                    $checkUserScheduleData = UserSchedule::where('user_id', $uid)->first();
                    if (empty($checkUserScheduleData)) {
                        $userScheduleObj = new UserSchedule;
                        $userScheduleObj->user_id = $uid;
                        $userScheduleObj->scheduled_by = Auth::user()->id;
                        $userScheduleObj->is_flexible = $is_flexible;
                        $userScheduleObj->is_repeat = $is_repeat;
                        $userScheduleObj->save();
                        $schedule_id = $userScheduleObj->id;
                    } else {
                        $schedule_id = $checkUserScheduleData->id;
                    }
                    $checkUserScheduleDetailsData = UserScheduleDetail::where('schedule_id', $schedule_id)
                        ->where('office_id', $office_id)
                        ->wheredate('schedule_from', $scheduleDate)
                        ->where('work_days', $dayNumberOfWeek)
                        ->exists();

                    if ($checkUserScheduleDetailsData) {
                        // Rollback the transaction if any schedule exists
                        DB::rollBack();

                        return response()->json(['message' => 'A schedule already exists, no data was inserted.'], 400);
                    }
                    // Store schedules in an array
                    $existingSchedules[$dayOfWeek][] = [
                        'office_id' => $office_id,
                        'schedule_id' => $schedule_id,
                        'lunch_duration' => $schedule['lunch_duration'],
                        'schedule_from' => $scheduleDate.' '.$schedule['schedule_from'],
                        'schedule_to' => $scheduleDate.' '.$schedule['schedule_to'],
                        'work_days' => $dayNumberOfWeek,
                        'repeated_batch' => 0,
                        'updated_type' => null,
                    ];
                }
                // return $existingSchedules;
                // Insert the schedules for selected days
                foreach ($existingSchedules as $daySchedules) {
                    // dd($daySchedules);
                    foreach ($daySchedules as $daySchedule) {
                        $checkExists = UserScheduleDetail::where('schedule_id', $schedule_id)
                            ->where('office_id', $office_id)
                            ->where('schedule_from', $scheduleDate.' '.$schedule['schedule_from'])
                            ->where('schedule_to', $scheduleDate.' '.$schedule['schedule_to'])
                            ->where('work_days', $dayNumberOfWeek)
                            ->exists();

                        if (! $checkExists) {
                            // echo '<br>'; echo 'inserted';
                            UserScheduleDetail::insertOrIgnore($daySchedule);
                        }
                        // echo '<br>'; echo 'exists '.$checkExists;
                    }
                }
                // return 'ok';
                // Create schedules for the days not included in the request
                foreach ($daysOfWeek as $day) {
                    if (! isset($existingSchedules[$day])) {
                        $weekDates = $this->getUserCurrentWeekStartAndEndDates();
                        // return $weekDates;
                        $day_no = config('constant.scheduling.day_no');
                        $result = array_flip($day_no);
                        $work_days = $result[$day];
                        $nextDate = $this->getUserNextDateForDay($day, $work_days, $weekDates);
                        // dd($day,$weekDates, $nextDate, $result);
                        UserScheduleDetail::insert([
                            'schedule_id' => $schedule_id,
                            'office_id' => $office_id,
                            'lunch_duration' => null,
                            'schedule_from' => $nextDate.' 00:00:00',
                            'schedule_to' => $nextDate.' 00:00:00',
                            'work_days' => $work_days,
                            'repeated_batch' => 0,
                            'updated_type' => null,
                        ]);
                    }
                }
            }

            // Commit the transaction
            DB::commit();

            return response()->json([
                'ApiName' => 'createScheduling',
                'status' => true,
                'message' => 'Schedules created successfully',
            ], 200);

        } catch (\Exception $e) {
            // Rollback the transaction in case of any error
            DB::rollBack();

            return response()->json([
                'ApiName' => 'createScheduling',
                'status' => false,
                'message' => 'An error occurred while creating schedules',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function getUserSchedules(Request $request)
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        Carbon::setWeekStartsAt(Carbon::SUNDAY); // Optional: Set custom start of the week
        Carbon::setWeekEndsAt(Carbon::SATURDAY); // Optional: Set custom end of the week
        $office_id = $request->office_id;

        if (! empty($request->start_date) && ! empty($request->end_date)) {
            $startOfWeek = Carbon::parse($request->start_date); // removed while code is merging
            $endOfWeek = Carbon::parse($request->end_date);
            $endOfWeek = $endOfWeek->endOfDay()->toDateTimeString();
        } else {
            $now = Carbon::now(); // removed while code is merging
            $startOfWeek = $now->startOfWeek()->startOfDay()->toDateString();
            $endOfWeek = $now->endOfWeek()->endOfDay()->toDateString();
        }
        if ($request->has('office_id') && $request->office_id != 'all') {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
                'user_schedule_details.is_flexible as is_flexible_flag',
                'user_schedule_details.is_worker_absent',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startOfWeek, $endOfWeek])
                ->where('user_schedule_details.office_id', $office_id)
                ->orderBy('users.id');
            // ->get();
        } else {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
                'user_schedule_details.is_flexible as is_flexible_flag',
                'user_schedule_details.is_worker_absent',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startOfWeek, $endOfWeek])
                    // ->where('user_schedule_details.office_id',$office_id)
                ->orderBy('users.id');
            // ->get();
        }
        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $userSchedulesData = $userSchedulesData->where(function ($query) use ($request) {
                $query->where('users.first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('users.last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$request->input('filter').'%'])
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%');
            });
        }
        $userSchedulesData = $userSchedulesData->get();
        // return $userSchedulesData->get();
        // $userSchedulesData = $userSchedulesData->paginate($perpage);
        // return $userSchedulesData;

        // Format the data as required
        $formattedData = [];
        $sumHours = 0;
        $sumMinutes = 0;
        $sumSeconds = 0;
        foreach ($userSchedulesData as $schedule) {
            $getFinalizeStatusData = $this->getFinalizeStatus($schedule);
            // dd($getFinalizeStatusData);
            $getUserData = User::find($schedule->user_id);
            $userData['id'] = ! empty($getUserData->id) ? $getUserData->id : null;
            $userData['first_name'] = ! empty($getUserData->first_name) ? $getUserData->first_name : null;
            $userData['middle_name'] = ! empty($getUserData->middle_name) ? $getUserData->middle_name : null;
            $userData['last_name'] = ! empty($getUserData->last_name) ? $getUserData->last_name : null;
            $userData['image'] = ! empty($getUserData->image) ? $getUserData->image : null;
            $userData['is_super_admin'] = ! empty($getUserData->is_super_admin) ? $getUserData->is_super_admin : null;
            $userData['is_manager'] = ! empty($getUserData->is_manager) ? $getUserData->is_manager : null;
            $userData['position_id'] = ! empty($getUserData->position_id) ? $getUserData->position_id : null;
            $userData['sub_position_id'] = ! empty($getUserData->sub_position_id) ? $getUserData->sub_position_id : null;
            $dayName = Carbon::parse($schedule->schedule_from)->format('l');
            $dayNumber = Carbon::parse($schedule->schedule_from)->format('N');
            // dd($dayName, $dayNumber, $schedule->schedule_from);
            $timeDifference = $this->calculateTimeDifference($schedule->schedule_from, $schedule->schedule_to);
            $total_hours = $this->calculateTotalHours($schedule->schedule_from, $schedule->schedule_to);
            // return $total_hours;
            // Accumulate total hours and minutes for each user
            if (! isset($formattedData[$schedule->user_id])) {
                $formattedData[$schedule->user_id]['totalSchedulesHours'] = 0;
                $formattedData[$schedule->user_id]['totalSchedulesMinutes'] = 0;
                $formattedData[$schedule->user_id]['totalWorkedHours'] = 0;
                $formattedData[$schedule->user_id]['totalWorkedMinutes'] = 0;
                // $formattedData[$schedule->user_id]['workedHours'] = "00:00:00";

            }
            $formattedData[$schedule->user_id]['totalSchedulesHours'] += $total_hours['hours'];
            $formattedData[$schedule->user_id]['totalSchedulesMinutes'] += $total_hours['minutes'];
            // dd($timeDifference);
            $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
            $schedule_from_time = $this->getTimeFromDateTime($schedule->schedule_from);
            $is_available = false;
            if ($schedule_from_time == '00:00:00') {
                $is_available = true;
            }
            $isPto = false;
            $ptoHours = null;
            $req_approvals_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();
            if (! empty($req_approvals_pto)) {
                $isPto = true;
                $ptoHours = $req_approvals_pto->pto_hours_perday;
            }
            $req_approvals_leave = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 7)
                ->where('status', 'Approved')
                ->first();
            $user_attendence = UserAttendance::where('user_id', $schedule->user_id)
                ->where('date', $schedule_from_date)
                ->first();
            $user_checkin = null;
            $user_checkout = null;
            $is_present = false;
            $is_late = false;
            $calculatedWorkedHours = '00:00:00';
            $user_attendence_status = false;
            $user_attendence_id = null;
            $lunchBreak = '00:00:00';
            $breakTime = '00:00:00';
            $req_approvals_data_id = null;
            $is_time_adjustment = false;
            $time_adjustment_id = null;
            if (! empty($user_attendence)) {
                $lunchBreak = isset($user_attendence->lunch_time) ? $user_attendence->lunch_time : null;
                $breakTime = isset($user_attendence->break_time) ? $user_attendence->break_time : null;
                // return $user_attendence;
                // $is_present = true;
                // $user_attendence_status = ($user_attendence->status) ? true : false;
                $user_attendence_status = ($schedule->attendance_status) ? true : false;
                $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    ->whereDate('attendance_date', $schedule_from_date)
                    ->where('adjustment_id', '>', 0)
                    ->first();
                if ($user_attendance_obj) {
                    $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                    $is_time_adjustment = true;
                    $time_adjustment_id = isset($get_request) ? $get_request->id : null;
                    $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                    $is_present = true;
                    $req_approvals_data_id = isset($get_request) ? $get_request->id : null;
                    $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : null;
                    $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : null;
                    if (! is_null($lunchBreak)) {
                        $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                    }
                    if (! is_null($breakTime)) {
                        $breakTime = gmdate('H:i:s', $breakTime * 60);
                    }

                } else {
                    $user_checkin_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        ->whereDate('attendance_date', $schedule_from_date)
                        ->where('type', 'clock in')
                        ->first();
                    // $user_checkin = $user_checkin_obj->attendance_date;
                    // $user_checkout_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    //     ->whereDate('attendance_date',$schedule_from_date)
                    //     ->where('type','clock out')
                    //     ->first();
                    // $user_checkout = $user_checkout_obj->attendance_date;
                    if (! empty($user_checkin_obj)) {
                        $is_present = true;
                        $user_checkin = $user_checkin_obj->attendance_date;
                        $user_checkout_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                            ->whereDate('attendance_date', $schedule_from_date)
                            ->where('type', 'clock out')
                            ->first();
                        if (! empty($user_checkout_obj)) {
                            $user_checkout = $user_checkout_obj->attendance_date;
                        } else {
                            $user_checkout = null;
                        }
                    } else {
                        $user_checkin = null;
                        $user_checkout = null;
                    }
                }
                $is_late = $this->compareDateTime($user_checkin, $schedule->schedule_from);
                $total_worked_hours = $this->calculateTotalHours($user_checkin, $user_checkout);
                // echo '<pre>'; print_r($total_worked_hours);
                // echo $schedule->user_id;
                $current_time = ! empty($user_attendence->current_time) ? $user_attendence->current_time : '00:00:00';
                $getSumWorkedHours = $this->getSumWorkedHours($current_time);
                // echo '<pre>'; print_r($getSumWorkedHours);
                $sumHours += $getSumWorkedHours['hours'];
                $sumMinutes += $getSumWorkedHours['minutes'];
                $sumSeconds += $getSumWorkedHours['seconds'];
                // echo $sumHours.'----'.$sumMinutes.'-----'.$sumSeconds;
                $calculatedWorkedHours = $this->convertToTimeFormat($sumHours, $sumMinutes, $sumSeconds);
                // $formattedData[$schedule->user_id]['workedHours'] =  $calculatedWorkedHours ?? "00:00:00";
                if (! empty($total_worked_hours) && ! is_null($total_worked_hours)) {
                    $formattedData[$schedule->user_id]['totalWorkedHours'] += isset($total_worked_hours) ? $total_worked_hours['hours'] : 0;
                    $formattedData[$schedule->user_id]['totalWorkedMinutes'] += isset($total_worked_hours) ? $total_worked_hours['minutes'] : 0;
                } else {
                    $formattedData[$schedule->user_id]['totalWorkedHours'] = 0;
                    $formattedData[$schedule->user_id]['totalWorkedMinutes'] = 0;
                }
            } else {
                $user_attendence_status = ($schedule->attendance_status) ? true : false;
                $req_approvals_data = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                    ->where('adjustment_date', '=', $schedule_from_date)
                    ->where('adjustment_type_id', 9)
                    ->where('status', 'Approved')
                    ->first();
                // echo $schedule_from_date;echo '<pre>';
                if (! empty($req_approvals_data)) {
                    $is_time_adjustment = true;
                    $time_adjustment_id = isset($req_approvals_data) ? $req_approvals_data->id : null;
                    $user_checkin = isset($req_approvals_data) ? $req_approvals_data->clock_in : null;
                    $user_checkout = isset($req_approvals_data) ? $req_approvals_data->clock_out : null;
                    $is_late = $this->compareDateTime($user_checkin, $schedule->schedule_from);
                    $is_present = true;
                    $lunchBreak = isset($req_approvals_data->lunch_adjustment) ? $req_approvals_data->lunch_adjustment : null;
                    $breakTime = isset($req_approvals_data->break_adjustment) ? $req_approvals_data->break_adjustment : null;
                    if (! is_null($lunchBreak)) {
                        $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                    }
                    if (! is_null($breakTime)) {
                        $breakTime = gmdate('H:i:s', $breakTime * 60);
                    }

                    $req_approvals_data_id = $req_approvals_data->id;
                }
                $total_worked_hours = $this->calculateTotalHours($user_checkin, $user_checkout);
                if (! empty($total_worked_hours) && ! is_null($total_worked_hours)) {
                    $formattedData[$schedule->user_id]['totalWorkedHours'] += isset($total_worked_hours) ? $total_worked_hours['hours'] : 0;
                    $formattedData[$schedule->user_id]['totalWorkedMinutes'] += isset($total_worked_hours) ? $total_worked_hours['minutes'] : 0;
                } else {
                    $formattedData[$schedule->user_id]['totalWorkedHours'] = 0;
                    $formattedData[$schedule->user_id]['totalWorkedMinutes'] = 0;
                }
            }
            // return [$req_approvals_pto,$req_approvals_leave];
            $formattedData[$schedule->user_id]['user_id'] = $schedule->user_id;
            $formattedData[$schedule->user_id]['user_data'] = ! empty($userData) ? $userData : null;
            $formattedData[$schedule->user_id]['is_flexible'] = $schedule->is_flexible;
            $formattedData[$schedule->user_id]['is_repeat'] = $schedule->is_repeat;
            // $formattedData[$schedule->user_id]['user_name'] = $schedule->first_name . ' ' . ($schedule->middle_name ?? '') . ' ' . $schedule->last_name;
            $formattedData[$schedule->user_id]['user_name'] = $schedule->first_name.' '.$schedule->last_name;
            $getSchedulesExists = UserSchedule::where('user_id', $schedule->user_id)->first();
            // return $getSchedulesExists;
            $latestScheduleDetailsDate = null;
            if (! empty($getSchedulesExists)) {
                $latestScheduleDetailsData = UserScheduleDetail::where('schedule_id', $getSchedulesExists->id)
                    ->where('office_id', $getUserData->office_id)
                    ->orderBy('id', 'desc')
                    ->first();
                // return $latestScheduleDetailsData;
                if (! empty($latestScheduleDetailsData)) {
                    $latestScheduleDetailsDate = $latestScheduleDetailsData->schedule_from;
                }
            }
            $formattedData[$schedule->user_id]['latestScheduleDetailsDate'] = $latestScheduleDetailsDate;
            $formattedData[$schedule->user_id]['schedules'][$dayName] = [
                'user_schedule_details_id' => $schedule->id,
                'user_attendence_id' => ! empty($user_attendence) ? $user_attendence->id : null,
                'lunch_duration' => $schedule->lunch_duration,
                'schedule_from' => $schedule->schedule_from,
                'schedule_to' => $schedule->schedule_to,
                'work_days' => $dayNumber,
                'day_name' => $dayName,
                'is_available' => $is_available,
                'clock_hours' => $timeDifference,
                'is_flexible' => $schedule->is_flexible_flag,
                // 'checkPTO' => !empty($req_approvals_pto) ? $req_approvals_pto->pto_per_day : false,
                'isPto' => $isPto,
                'checkPTO' => $ptoHours,
                'checkLeave' => ! empty($req_approvals_leave) ? true : false,
                'clockIn' => ! empty($user_checkin) ? $user_checkin : null,
                'clockOut' => ! empty($user_checkout) ? $user_checkout : null,
                'isPresent' => $is_present,
                'isLate' => $is_late,
                'user_attendence_status' => $user_attendence_status,
                'user_attendence_approved_status' => $user_attendence_status,
                'payFequency' => $getFinalizeStatusData['frequency'],
                'finalizeStatus' => $getFinalizeStatusData['finalizeStatus'],
                'lunchBreak' => isset($lunchBreak) ? $lunchBreak : '00:00:00',
                'breakTime' => isset($breakTime) ? $breakTime : '00:00:00',
                'executeStatus' => $getFinalizeStatusData['executeStatus'],
                'is_time_adjustment' => $is_time_adjustment,
                'time_adjustment_id' => $time_adjustment_id,
                'is_worker_absent' => $schedule->is_worker_absent,
            ];
        }
        // Sort the schedules by work_days within each user's schedule
        $attendence_array = [];

        foreach ($formattedData as &$userData) {
            // echo '<pre>'; print_r($userData);
            usort($userData['schedules'], function ($a, $b) {
                // return $a['work_days'] <=> $b['work_days'];
                return $a['schedule_from'] <=> $b['schedule_from'];
            });
            // calculate scheddules hours and total hous
            $totalHours = $userData['totalSchedulesHours'];
            $totalMinutes = $userData['totalSchedulesMinutes'];
            $userData['totalSchedulesHours'] = floor($totalHours + ($totalMinutes / 60));
            $userData['totalSchedulesMinutes'] = $totalMinutes % 60;

            $totalWHours = $userData['totalWorkedHours'];
            $totalWMinutes = $userData['totalWorkedMinutes'];
            $userData['totalWorkedHours'] = floor($totalWHours + ($totalWMinutes / 60));
            $userData['totalWorkedMinutes'] = $totalWMinutes % 60;
            // $userData['workedHours'] = $this->getCalTotalWorkedHour($userData['totalWorkedHours'],$userData['totalWorkedMinutes']);
            $userDataWorkedHours = $this->getCalTotalWorkedHour($userData['totalWorkedHours'], $userData['totalWorkedMinutes']);
            $totalLunchreakSeconds = 0;
            foreach ($userData['schedules'] as $sch) {
                // echo '<pre>'; print_r($sch);
                $lunchBreakSeconds = Carbon::parse($sch['lunchBreak']);
                $breakTimeSeconds = Carbon::parse($sch['breakTime']);
                $totalLunchreakSeconds += $lunchBreakSeconds->secondsSinceMidnight() + $breakTimeSeconds->secondsSinceMidnight();
            }
            $userData['totalLunchBreakTime'] = gmdate('H:i:s', $totalLunchreakSeconds);
            $totalLunchBreakTime = gmdate('H:i:s', $totalLunchreakSeconds);
            $getDiffWorkedHours = $this->calculateGetDiffWorkedHours($userDataWorkedHours, $totalLunchBreakTime);
            $userData['workedHours'] = $getDiffWorkedHours;
            // dump($userData['workedHours']);

            // array_push($attendence_array,$userData['user_attendence_status']);
        }
        // Extract schedules from all users
        $schedules = array_column($formattedData, 'schedules');
        // dd($schedules);
        // Flatten the schedules array
        $flattenedSchedules = array_merge(...$schedules);

        // Extract the user_attendence_status values
        // $userAttendenceStatuses = array_column($flattenedSchedules, 'user_attendence_status');
        $userAttendenceStatuses = array_column($flattenedSchedules, 'user_attendence_approved_status');

        // Output the attendance status array
        // print_r($userAttendenceStatuses);
        // $userAttendenceStatuses = [1,1, 0,1,0];
        // print_r($userAttendenceStatuses);
        $checkUserAttendenceStatuses = in_array(0, $userAttendenceStatuses);
        // dd($attendence_array);
        // foreach ($formattedData as &$userData) {
        //     $totalHours = $userData['totalSchedulesHours'];
        //     $totalMinutes = $userData['totalSchedulesMinutes'];
        //     $userData['totalSchedulesHours'] = floor($totalHours + ($totalMinutes / 60));
        //     $userData['totalSchedulesMinutes'] = $totalMinutes % 60;

        //     $totalWHours = $userData['totalWorkedHours'];
        //     $totalWMinutes = $userData['totalWorkedMinutes'];
        //     $userData['totalWorkedHours'] = floor($totalWHours + ($totalWMinutes / 60));
        //     $userData['totalWorkedMinutes'] = $totalWMinutes % 60;
        // }
        // Prepare the response
        // $formattedData = $formattedData->paginate($perpage);
        $formattedData = $this->paginates($formattedData, $perpage);
        if (! empty($formattedData)) {
            return response()->json([
                'ApiName' => 'getUserSchedules',
                'status' => true,
                'attendenceApprovedStatus' => ($checkUserAttendenceStatuses) ? 'Not Approved' : 'Approved',
                'data' => $formattedData, // Resetting array keys to start from 0
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'getUserSchedules',
                'status' => true,
                'attendenceApprovedStatus' => ($checkUserAttendenceStatuses) ? 'Not Approved' : 'Approved',
                'message' => 'No schedules found for the specified week.',
                'data' => [],
            ], 200);
        }
        // return $userSchedulesData;
    }

    public function userSchedulesExport(Request $request)
    {
        Carbon::setWeekStartsAt(Carbon::MONDAY); // Optional: Set custom start of the week
        Carbon::setWeekEndsAt(Carbon::SUNDAY); // Optional: Set custom end of the week
        $location = $request->office_id;

        if (! empty($request->start_date) && ! empty($request->end_date)) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
        } else {
            $now = Carbon::now();
            $startDate = $now->startOfWeek()->toDateString();
            $endDate = $now->endOfWeek()->toDateString();
        }
        /*$userSchedulesData = UserSchedule::select(
            'users.id as user_id',
            'users.first_name',
            'users.middle_name',
            'users.last_name',
            'user_schedule_details.lunch_duration',
            'user_schedule_details.schedule_from',
            'user_schedule_details.schedule_to',
            'user_schedule_details.work_days',
            'user_schedule_details.office_id'
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startDate, $endDate])
                ->where('user_schedule_details.office_id',$location)
                ->orderBy('users.id')
                ->get();

            // Format the data as required
            //return $userSchedulesData;
            $formattedData = [];
            foreach ($userSchedulesData as $schedule) {
                $dayName = Carbon::parse($schedule->schedule_from)->format('l');
                $dayNumber = Carbon::parse($schedule->schedule_from)->format('N');
                $timeDifference = $this->calculateTimeDifference($schedule->schedule_from, $schedule->schedule_to);
                //dd($timeDifference);
                $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
                $req_approvals_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                    ->where('start_date', '<=', $schedule_from_date)
                    ->where('end_date', '>=', $schedule_from_date)
                    ->where('adjustment_type_id', 8)
                    ->first();

                $req_approvals_leave = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                    ->where('start_date', '<=', $schedule_from_date)
                    ->where('end_date', '>=', $schedule_from_date)
                    ->where('adjustment_type_id', 7)
                    ->first();
                //return [$req_approvals_pto,$req_approvals_leave];
                $formattedData[] = [
                    'UserId'            => $schedule->user_id,
                    'UserName'          => $schedule->first_name . ' ' . ($schedule->middle_name ?? '') . ' ' . $schedule->last_name,
                    'lunch_duration'    => $schedule->lunch_duration,
                    'lunch_duration'    => $schedule->lunch_duration,
                    'schedule_from'     => $schedule->schedule_from,
                    'schedule_to'       => $schedule->schedule_to,
                    'work_days'         => $dayNumber,
                    'day_name'          => $dayName,
                    'clock_hours'       => $timeDifference,
                    'checkPTO'          => !empty($req_approvals_pto) ? $req_approvals_pto->pto_per_day : false,
                    'checkLeave'        => !empty($req_approvals_leave) ? true : false,
                ];
            }
        return $formattedData;*/
        $file_name = 'schedules_export_'.date('Y_m_d_H_i_s').'.xlsx';

        if ($location != '' && $startDate != '' && $endDate != '') {
            $res = Excel::store(new SchedulesExport($location, $startDate, $endDate),
                'exports/reports/schedules/'.$file_name,
                'public',
                \Maatwebsite\Excel\Excel::XLSX);
            // return $res;
        }
        // else{
        //     Excel::store(new SchedulesExport(),
        //     'exports/reports/costs/'.$file_name,
        //     'public',
        //     \Maatwebsite\Excel\Excel::XLSX);
        // }
        $url = getStoragePath('exports/reports/schedules/'.$file_name);

        // $url = getExportBaseUrl().'storage/exports/reports/schedules/' . $file_name;
        // Get the URL for the stored file
        // Return the URL in the API response
        return response()->json(['url' => $url]);

    }

    public function w2UserList(Request $request): JsonResponse
    {
        $request->validate([
            'office_id' => 'required',
        ]);
        // $getData = User::select('users.id', 'users.first_name','users.middle_name','users.last_name')
        //                 ->join('positions','users.position_id','=','positions.id')
        //                 ->where('positions.worker_type','w2');
        $getData = User::select('users.id', 'users.first_name', 'users.middle_name', 'users.last_name', 'position_wages.wages_status')
            ->join('position_wages', 'position_wages.position_id', '=', 'users.sub_position_id')
                        // ->where('users.worker_type','w2') // show listing with wages for w2 and 1099 both
            ->where('position_wages.wages_status', 1)
            ->where('users.dismiss', 0);
        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $getData = $getData->where(function ($query) use ($request) {
                $query->where('users.first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('users.last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$request->input('filter').'%'])
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%');
            });
        }
        if ($request->has('office_id') && $request->office_id != 'all') {
            $data = $getData->where('office_id', $request->office_id)->get();
        } else {
            $data = $getData->get();
        }

        // Prepare the response
        if (! empty($data)) {
            return response()->json([
                'ApiName' => 'w2UserList',
                'status' => true,
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'w2UserList',
                'status' => true,
                'message' => 'No w2 employee list found.',
                'data' => [],
            ], 200);
        }
    }

    public function updateUserScheduleFlexible(UpdateUserScheduleFlexibleRequest $request)
    {
        $user_id = $request->user_id;
        $is_flexible = $request->is_flexible;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $findUserSchedule = UserSchedule::where('user_id', $user_id)->first();
        $userData = User::find($user_id);
        $office_id = null;
        if (! empty($userData)) {
            if (isset($userData->office_id) && ! empty($userData->office_id)) {
                $office_id = $userData->office_id;
            }
        }
        // return $findUserSchedule;
        if (! empty($findUserSchedule)) {
            $schedule_id = $findUserSchedule->id;
            $findUserSchedule->is_flexible = $is_flexible;
            $findUserSchedule->save();
            $scheduleDetailsData = UserScheduleDetail::where('schedule_id', $schedule_id)
                ->whereDate('schedule_from', '>=', $startDate)
                ->whereDate('schedule_to', '<=', $endDate)
                                    // ->dd();
                ->get();
            // return $scheduleDetailsData;
            if (! empty($scheduleDetailsData) && count($scheduleDetailsData) > 0) {
                // $scheduleDetailsData = array_map(function($schedule) use($is_flexible) {
                //     $schedule['is_flexible'] = $is_flexible;  // Add or update the 'is_flexible' property
                //     return $schedule;
                // }, $scheduleDetailsData);
                foreach ($scheduleDetailsData as $schedule) {
                    $schedule->is_flexible = $is_flexible;
                    $schedule->save();
                }
            }

            return response()->json([
                'ApiName' => 'updateUserScheduleFlexible',
                'Message' => "User's Schedule Flexible status is updated",
                'status' => true,
                // 'data' => $findUserSchedule,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'updateUserScheduleFlexible',
                'Message' => "User's Schedule not found",
                'status' => false,
            ], 400);
        }

    }

    public function getSchedulesWithCheckinCheckout(Request $request)
    {
        if (! empty($request->start_date) && ! empty($request->end_date)) {
            $start_of_month = $request->start_date;
            $end_of_month = $request->end_date;
        } elseif ($request->has('filter') && ! empty($request->filter)) {
            $filer = $request->filer;
            $monthDates = $this->getMonthStartAndEndDates($request->filter);
            $start_of_month = $monthDates['start_of_month'];
            $end_of_month = $monthDates['end_of_month'];

        } else {
            $monthDates = $this->getMonthStartAndEndDates(Carbon::now());
            $start_of_month = $monthDates['start_of_month'];
            $end_of_month = $monthDates['end_of_month'];
        }
        // return [$start_of_month,$end_of_month];
        $user_id = $request->user_id;
        $getUserData = User::find($user_id);
        $userData['id'] = ! empty($getUserData->id) ? $getUserData->id : null;
        $userData['first_name'] = ! empty($getUserData->first_name) ? $getUserData->first_name : null;
        $userData['middle_name'] = ! empty($getUserData->middle_name) ? $getUserData->middle_name : null;
        $userData['last_name'] = ! empty($getUserData->last_name) ? $getUserData->last_name : null;
        $userData['image'] = ! empty($getUserData->image) ? $getUserData->image : null;
        $userData['is_super_admin'] = ! empty($getUserData->is_super_admin) ? $getUserData->is_super_admin : null;
        $userData['is_manager'] = ! empty($getUserData->is_manager) ? $getUserData->is_manager : null;
        $userData['position_id'] = ! empty($getUserData->position_id) ? $getUserData->position_id : null;
        $userData['sub_position_id'] = ! empty($getUserData->sub_position_id) ? $getUserData->sub_position_id : null;
        // return [$start_of_month, $end_of_month];
        Carbon::setWeekStartsAt(Carbon::SUNDAY); // Optional: Set custom start of the week
        Carbon::setWeekEndsAt(Carbon::SATURDAY); // Optional: Set custom end of the week
        $location = $request->office_id;
        $userSchedulesData = UserSchedule::select(
            'users.id as user_id',
            'users.first_name',
            'users.middle_name',
            'users.last_name',
            'user_schedules.is_flexible',
            'user_schedules.is_repeat',
            'user_schedule_details.id',
            'user_schedule_details.lunch_duration',
            'user_schedule_details.schedule_from',
            'user_schedule_details.schedule_to',
            'user_schedule_details.work_days',
            'user_schedule_details.office_id',
            'user_schedule_details.user_attendance_id',
            'user_schedule_details.attendance_status',
            'user_schedule_details.is_flexible as is_flexible_flag',
            'user_schedule_details.is_worker_absent',
        )
            ->join('users', 'user_schedules.user_id', '=', 'users.id')
            ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                // ->whereBetween('user_schedule_details.schedule_from', [$start_of_month, $end_of_month])
            ->whereBetween(DB::raw('DATE(user_schedule_details.schedule_from)'), [$start_of_month, $end_of_month])
            ->where('user_schedules.user_id', '=', $user_id)
                // ->where('user_schedule_details.office_id',$getUserData->office_id)
            ->orderBy('users.id')
            ->get();

        // Format the data as required
        // return $userSchedulesData;
        $formattedData = [];
        $sumHours = 0;
        $sumMinutes = 0;
        $sumSeconds = 0;
        foreach ($userSchedulesData as $schedule) {
            $getFinalizeStatusData = $this->getFinalizeStatus($schedule);
            // dd($getFinalizeStatusData);
            $dayName = Carbon::parse($schedule->schedule_from)->format('l');
            $dayNumber = Carbon::parse($schedule->schedule_from)->format('N');
            $timeDifference = $this->calculateTimeDifference($schedule->schedule_from, $schedule->schedule_to);
            $total_hours = $this->calculateTotalHours($schedule->schedule_from, $schedule->schedule_to);
            // dd($timeDifference);
            if (! isset($formattedData[$schedule->user_id])) {
                $formattedData[$schedule->user_id]['totalSchedulesHours'] = 0;
                $formattedData[$schedule->user_id]['totalSchedulesMinutes'] = 0;
                $formattedData[$schedule->user_id]['totalWorkedHours'] = 0;
                $formattedData[$schedule->user_id]['totalWorkedMinutes'] = 0;
                $formattedData[$schedule->user_id]['workedHours'] = '00:00:00';

            }
            $formattedData[$schedule->user_id]['totalSchedulesHours'] += $total_hours['hours'];
            $formattedData[$schedule->user_id]['totalSchedulesMinutes'] += $total_hours['minutes'];
            $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
            $schedule_from_time = $this->getTimeFromDateTime($schedule->schedule_from);
            $is_available = false;
            if ($schedule_from_time == '00:00:00') {
                $is_available = true;
            }
            $isPto = false;
            $ptoHours = null;
            $req_approvals_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();
            if (! empty($req_approvals_pto)) {
                $isPto = true;
                $ptoHours = $req_approvals_pto->pto_hours_perday;
            }
            $req_approvals_leave = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 7)
                ->where('status', 'Approved')
                ->first();
            $user_attendence = UserAttendance::where('user_id', $schedule->user_id)
                ->where('date', $schedule_from_date)
                ->first();
            $user_checkin = null;
            $user_checkout = null;
            $is_present = false;
            $is_late = false;
            $calculatedWorkedHours = '00:00:00';
            $checkInTimeDifference = null;
            $user_attendence_status = false;
            $user_attendence_id = null;
            $lunchBreak = '00:00:00';
            $breakTime = '00:00:00';
            $req_approvals_data_id = null;
            $is_time_adjustment = false;
            $time_adjustment_id = null;
            if (! empty($user_attendence)) {
                $lunchBreak = isset($user_attendence->lunch_time) ? $user_attendence->lunch_time : null;
                $breakTime = isset($user_attendence->break_time) ? $user_attendence->break_time : null;
                // $is_present = true;
                // $user_attendence_status = ($user_attendence->status) ? true : false;
                $user_attendence_status = ($schedule->attendance_status == 1) ? true : false;
                $user_attendence_id = $user_attendence->id;
                // echo $user_attendence_status."  ------  ". $schedule_from_date."\n";
                // $user_checkin = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                //     ->whereDate('attendance_date',$schedule_from_date)
                //     ->where('type','clock in')
                //     ->first();
                // $user_checkout = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                //     ->whereDate('attendance_date',$schedule_from_date)
                //     ->where('type','clock out')
                //     ->first();
                $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    ->whereDate('attendance_date', $schedule_from_date)
                    ->where('adjustment_id', '>', 0)
                    ->first();
                if ($user_attendance_obj) {
                    $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                    $is_time_adjustment = true;
                    $time_adjustment_id = isset($get_request) ? $get_request->id : null;
                    $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                    $is_present = true;
                    $req_approvals_data_id = isset($get_request) ? $get_request->id : null;
                    $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : null;
                    $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : null;
                    if (! is_null($lunchBreak)) {
                        $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                    }
                    if (! is_null($breakTime)) {
                        $breakTime = gmdate('H:i:s', $breakTime * 60);
                    }
                } else {
                    $user_checkin_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        ->whereDate('attendance_date', $schedule_from_date)
                        ->where('type', 'clock in')
                        ->first();
                    if (! empty($user_checkin_obj)) {
                        $is_present = true;
                        $user_checkin = $user_checkin_obj->attendance_date;
                        $user_checkout_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                            ->whereDate('attendance_date', $schedule_from_date)
                            ->where('type', 'clock out')
                            ->first();
                        // $user_checkout = $user_checkout_obj->attendance_date;
                        if (! empty($user_checkout_obj)) {
                            $user_checkout = $user_checkout_obj->attendance_date;
                        } else {
                            $user_checkout = null;
                        }
                    } else {
                        $user_checkin = null;
                        $user_checkout = null;
                    }

                }
                $checkInTimeDifference = $this->calculateTimeDifference($user_checkin, $user_checkout);
                $is_late = $this->compareDateTime($user_checkin, $schedule->schedule_from);
                $total_worked_hours = $this->calculateTotalHours($user_checkin, $user_checkout);
                // $current_time = $user_attendence->current_time;
                $current_time = ! empty($user_attendence->current_time) ? $user_attendence->current_time : '00:00:00';
                $getSumWorkedHours = $this->getSumWorkedHours($current_time);
                // echo '<pre>'; print_r($getSumWorkedHours);
                $sumHours += $getSumWorkedHours['hours'];
                $sumMinutes += $getSumWorkedHours['minutes'];
                $sumSeconds += $getSumWorkedHours['seconds'];
                // echo $sumHours.'----'.$sumMinutes.'-----'.$sumSeconds.'------- '.$user_checkin->attendance_date;
                $calculatedWorkedHours = $this->convertToTimeFormat($sumHours, $sumMinutes, $sumSeconds);
                // echo '=====>  '.$calculatedWorkedHours.'<\n>';
                $formattedData[$schedule->user_id]['workedHours'] = $calculatedWorkedHours ?? '00:00:00';
                if (! empty($total_worked_hours) && ! is_null($total_worked_hours)) {
                    $formattedData[$schedule->user_id]['totalWorkedHours'] += isset($total_worked_hours) ? $total_worked_hours['hours'] : 0;
                    $formattedData[$schedule->user_id]['totalWorkedMinutes'] += isset($total_worked_hours) ? $total_worked_hours['minutes'] : 0;
                } else {
                    $formattedData[$schedule->user_id]['totalWorkedHours'] = 0;
                    $formattedData[$schedule->user_id]['totalWorkedMinutes'] = 0;
                }
            } else {
                $user_attendence_status = ($schedule->attendance_status == 1) ? true : false;
                $req_approvals_data = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                    ->where('adjustment_date', '=', $schedule_from_date)
                    ->where('adjustment_type_id', 9)
                    ->where('status', 'Approved')
                    ->first();
                // echo $schedule_from_date;echo '<pre>';
                if (! empty($req_approvals_data)) {
                    $is_time_adjustment = true;
                    $time_adjustment_id = isset($req_approvals_data) ? $req_approvals_data->id : null;
                    $user_checkin = isset($req_approvals_data) ? $req_approvals_data->clock_in : null;
                    $user_checkout = isset($req_approvals_data) ? $req_approvals_data->clock_out : null;
                    $is_late = $this->compareDateTime($user_checkin, $schedule->schedule_from);
                    $is_present = true;
                    $lunchBreak = isset($req_approvals_data->lunch_adjustment) ? $req_approvals_data->lunch_adjustment : null;
                    $breakTime = isset($req_approvals_data->break_adjustment) ? $req_approvals_data->break_adjustment : null;
                    if (! is_null($lunchBreak)) {
                        $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                    }
                    if (! is_null($breakTime)) {
                        $breakTime = gmdate('H:i:s', $breakTime * 60);
                    }
                    $req_approvals_data_id = $req_approvals_data->id;
                }
            }

            // return [$schedule_from_date,$user_checkin,$user_checkout];
            // return [$req_approvals_pto,$req_approvals_leave];
            $formattedData[$schedule->user_id]['user_id'] = $schedule->user_id;
            $formattedData[$schedule->user_id]['user_data'] = ! empty($userData) ? $userData : null;
            $formattedData[$schedule->user_id]['is_flexible'] = $schedule->is_flexible;
            $formattedData[$schedule->user_id]['is_repeat'] = $schedule->is_repeat;
            $formattedData[$schedule->user_id]['user_name'] = $schedule->first_name.' '.$schedule->last_name;
            $formattedData[$schedule->user_id]['schedules'][] = [
                'user_schedule_details_id' => $schedule->id,
                'lunch_duration' => $schedule->lunch_duration,
                'schedule_from' => $schedule->schedule_from,
                'schedule_to' => $schedule->schedule_to,
                'work_days' => $dayNumber,
                'day_name' => $dayName,
                'is_available' => $is_available,
                'clock_hours' => $timeDifference,
                'is_flexible' => $schedule->is_flexible_flag,
                // 'checkPTO'          => !empty($req_approvals_pto) ? $req_approvals_pto->pto_per_day : false,
                'isPto' => $isPto,
                'checkPTO' => $ptoHours,
                'checkLeave' => ! empty($req_approvals_leave) ? true : false,
                'clockIn' => ! empty($user_checkin) ? $user_checkin : null,
                'clockOut' => ! empty($user_checkout) ? $user_checkout : null,
                'checkInClockHours' => ! empty($checkInTimeDifference) ? $checkInTimeDifference : null,
                'isPresent' => $is_present,
                'isLate' => $is_late,
                'user_attendence_status' => $user_attendence_status,
                'user_attendence_id' => $user_attendence_id,
                'user_attendence_approved_status' => $user_attendence_status,
                'payFequency' => $getFinalizeStatusData['frequency'],
                'finalizeStatus' => $getFinalizeStatusData['finalizeStatus'],
                'lunchBreak' => isset($lunchBreak) ? $lunchBreak : '00:00:00',
                'breakTime' => isset($breakTime) ? $breakTime : '00:00:00',
                'executeStatus' => $getFinalizeStatusData['executeStatus'],
                'is_time_adjustment' => $is_time_adjustment,
                'time_adjustment_id' => $time_adjustment_id,
                'is_worker_absent' => $schedule->is_worker_absent,
            ];
            // $formattedData[] = [
            //     'UserId'            => $schedule->user_id,
            //     'UserName'          => $schedule->first_name . ' ' . ($schedule->middle_name ?? '') . ' ' . $schedule->last_name,
            //     'lunch_duration'    => $schedule->lunch_duration,
            //     'lunch_duration'    => $schedule->lunch_duration,
            //     'schedule_from'     => $schedule->schedule_from,
            //     'schedule_to'       => $schedule->schedule_to,
            //     'work_days'         => $dayNumber,
            //     'day_name'          => $dayName,
            //     'clock_hours'       => $timeDifference,
            //     'checkPTO'          => !empty($req_approvals_pto) ? $req_approvals_pto->pto_per_day : false,
            //     'checkLeave'        => !empty($req_approvals_leave) ? true : false,
            //     'clockIn'           => !empty($user_checkin) ? $user_checkin->attendance_date : null,
            //     'clockOut'          => !empty($user_checkout) ? $user_checkout->attendance_date : null,
            //     //'clockIn'           => $user_checkin,
            //     //'clockOut'          => $user_checkout,
            // ];
            // Week-wise aggregation
            // $weekNumber = Carbon::parse($schedule->schedule_from)->format('W'); // Get the week number
            $weekNumber = Carbon::parse($schedule->schedule_from)->startOfWeek(Carbon::SUNDAY)->format('W'); // Get the week number
            $weekNumber2 = Carbon::parse($schedule->schedule_from)->weekOfMonth; // Get the week number of the month
            $weekStart = Carbon::parse($schedule->schedule_from)->startOfWeek()->toDateString();
            $weekEnd = Carbon::parse($schedule->schedule_from)->endOfWeek()->toDateString();
            if (! isset($formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber])) {
                $formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber] = [
                    'totalSchedulesHours' => 0,
                    'totalSchedulesMinutes' => 0,
                    'totalWorkedHours' => 0,
                    'totalWorkedMinutes' => 0,
                    'weekNumber' => $weekNumber,
                    'startWeek' => $weekStart,
                    'endWeek' => $weekEnd,
                    'user_id' => $user_id,
                ];
            }
            $formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber]['totalSchedulesHours'] += $total_hours['hours'];
            $formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber]['totalSchedulesMinutes'] += $total_hours['minutes'];
            $formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber]['totalWorkedHours'] = $total_worked_hours['hours'] ?? 0;
            $formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber]['totalWorkedMinutes'] = $total_worked_hours['minutes'] ?? 0;
        }
        // return $formattedData;
        foreach ($formattedData as &$userData) {
            usort($userData['schedules'], function ($a, $b) {
                return $a['schedule_from'] <=> $b['schedule_from'];
            });

            // calculating total hours, schedules hours
            $totalHours = $userData['totalSchedulesHours'];
            $totalMinutes = $userData['totalSchedulesMinutes'];
            $userData['totalSchedulesHours'] = floor($totalHours + ($totalMinutes / 60));
            $userData['totalSchedulesMinutes'] = $totalMinutes % 60;

            $totalWHours = $userData['totalWorkedHours'];
            $totalWMinutes = $userData['totalWorkedMinutes'];
            $userData['totalWorkedHours'] = floor($totalWHours + ($totalWMinutes / 60));
            $userData['totalWorkedMinutes'] = $totalWMinutes % 60;
            // calculating weekly calculation
            $weeklyTotals = array_values($userData['weeklyTotals']);
            foreach ($weeklyTotals as &$weeklyData) {
                // echo '<pre>'; print_r($weeklyData);
                $totalWeeklyHours = $weeklyData['totalSchedulesHours'];
                $totalWeeklyMinutes = $weeklyData['totalSchedulesMinutes'];
                $weeklyData['totalSchedulesHours'] = floor($totalWeeklyHours + ($totalWeeklyMinutes / 60));
                $weeklyData['totalSchedulesMinutes'] = $totalWeeklyMinutes % 60;
                // echo $totalWeeklyHours."\n".$totalWeeklyMinutes."---".$weeklyData['totalSchedulesHours']."----".$weeklyData['totalSchedulesMinutes'];
                $totalWolyHours = $weeklyData['totalWorkedHours'];
                $totalWoMinutes = $weeklyData['totalWorkedMinutes'];
                $weeklyData['totalWorkedHours'] = floor($totalWolyHours + ($totalWoMinutes / 60));
                $weeklyData['totalWorkedMinutes'] = $totalWoMinutes % 60;
                // echo $totalWolyHours."\n".$totalWoMinutes."---".$weeklyData['totalWorkedHours']."----".$weeklyData['totalWorkedMinutes'];
                $getWeeklyWorkedHourse = $this->weeklyWorkedHourse($weeklyData['user_id'], $weeklyData['startWeek'], $weeklyData['endWeek']);
                $weeklyData['weeklyWorkedHours'] = $getWeeklyWorkedHourse ?? '00:00:00';
                // dd($getWeeklyWorkedHourse);
                $weeklyData['workedHours'] = $this->convertToTimeFormatWeekly($weeklyData['totalWorkedHours'], $weeklyData['totalWorkedMinutes']);
            }
            $userData['weeklyTotals'] = $weeklyTotals;
        }
        // Extract schedules from all users
        $schedules = array_column($formattedData, 'schedules');
        // dd($schedules);
        // Flatten the schedules array
        $flattenedSchedules = array_merge(...$schedules);

        // Extract the user_attendence_status values
        // $userAttendenceStatuses = array_column($flattenedSchedules, 'user_attendence_status');
        $userAttendenceStatuses = array_column($flattenedSchedules, 'user_attendence_approved_status');
        $checkUserAttendenceStatuses = in_array(0, $userAttendenceStatuses);
        // foreach ($formattedData as &$userData) {
        //     $totalHours = $userData['totalSchedulesHours'];
        //     $totalMinutes = $userData['totalSchedulesMinutes'];
        //     $userData['totalSchedulesHours'] = floor($totalHours + ($totalMinutes / 60));
        //     $userData['totalSchedulesMinutes'] = $totalMinutes % 60;

        //     $totalWHours = $userData['totalWorkedHours'];
        //     $totalWMinutes = $userData['totalWorkedMinutes'];
        //     $userData['totalWorkedHours'] = floor($totalWHours + ($totalWMinutes / 60));
        //     $userData['totalWorkedMinutes'] = $totalWMinutes % 60;
        // }

        // Reformat weeklyTotals to a sequential array and calculate workedHours string
        // foreach ($formattedData as &$userData) {
        //     $weeklyTotals = array_values($userData['weeklyTotals']);
        //     foreach ($weeklyTotals as &$weeklyData) {
        //         //echo '<pre>'; print_r($weeklyData);
        //         $getWeeklyWorkedHourse = $this->weeklyWorkedHourse($weeklyData['user_id'], $weeklyData['startWeek'], $weeklyData['endWeek']);
        //         $weeklyData['weeklyWorkedHours'] = $getWeeklyWorkedHourse ?? "00:00:00";
        //         //dd($getWeeklyWorkedHourse);
        //         $weeklyData['workedHours'] = $this->convertToTimeFormatWeekly($weeklyData['totalWorkedHours'], $weeklyData['totalWorkedMinutes']);
        //     }
        //     $userData['weeklyTotals'] = $weeklyTotals;
        // }
        // Prepare the response
        // return array_values($formattedData);
        $formattedDataArray = array_values($formattedData);
        $formattedData = $this->getWeeklyCalculationWorkedHous($formattedDataArray);
        // return $formattedData;
        if (! empty($formattedData)) {
            return response()->json([
                'ApiName' => 'getSchedulesWithCheckinCheckout',
                'status' => true,
                'attendenceApprovedStatus' => ($checkUserAttendenceStatuses) ? 'Not Approved' : 'Approved',
                'data' => array_values($formattedData), // Resetting array keys to start from 0
            ], 200);
        } else {
            $user_id = $request->user_id;
            $getUserData = User::find($user_id);
            $userData['id'] = ! empty($getUserData->id) ? $getUserData->id : null;
            $userData['first_name'] = ! empty($getUserData->first_name) ? $getUserData->first_name : null;
            $userData['middle_name'] = ! empty($getUserData->middle_name) ? $getUserData->middle_name : null;
            $userData['last_name'] = ! empty($getUserData->last_name) ? $getUserData->last_name : null;
            $userData['image'] = ! empty($getUserData->image) ? $getUserData->image : null;
            $userData['is_super_admin'] = ! empty($getUserData->is_super_admin) ? $getUserData->is_super_admin : null;
            $userData['is_manager'] = ! empty($getUserData->is_manager) ? $getUserData->is_manager : null;
            $userData['position_id'] = ! empty($getUserData->position_id) ? $getUserData->position_id : null;
            $userData['sub_position_id'] = ! empty($getUserData->sub_position_id) ? $getUserData->sub_position_id : null;
            $formattedData = [];
            $formattedData[$user_id]['user_id'] = $user_id;
            // $formattedData[$user_id]['user_name'] = $getUserData->first_name ?? null . ' ' . $getUserData->last_name ?? null;
            $formattedData[$user_id]['user_name'] = $userData['first_name'].' '.$userData['last_name'];
            $formattedData[$user_id]['user_data'] = ! empty($userData) ? $userData : null;

            return response()->json([
                'ApiName' => 'getSchedulesWithCheckinCheckout',
                'status' => true,
                'attendenceApprovedStatus' => ($checkUserAttendenceStatuses) ? 'Not Approved' : 'Approved',
                'message' => 'No schedules found for the specified week.',
                // 'data' => [],
                'data' => array_values($formattedData), // Resetting array keys to start from 0
            ], 200);
        }
    }

    public function getWeeklyCalculationWorkedHous($formattedDataArray)
    {
        // return $formattedDataArray ;
        // $formattedDataArray = [];
        if (count($formattedDataArray) > 0) {
            foreach ($formattedDataArray[0]['weeklyTotals'] as &$weeklyTotal) {
                $totalWorkedSeconds = 0;
                $totalSubtractedSchedulesMinutes = 0;
                foreach ($formattedDataArray[0]['schedules'] as $schedule) {
                    $scheduleDate = Carbon::parse($schedule['schedule_from'])->format('Y-m-d');

                    // Check if the schedule date is within the current weekly total's date range
                    if ($scheduleDate >= $weeklyTotal['startWeek'] && $scheduleDate <= $weeklyTotal['endWeek']) {

                        if ($schedule['clockIn'] && $schedule['clockOut']) {
                            $clockIn = Carbon::parse($schedule['clockIn']);
                            $clockOut = Carbon::parse($schedule['clockOut']);

                            // Calculate worked time in seconds
                            $workedSeconds = $clockOut->diffInSeconds($clockIn);

                            // Subtract lunch and break time in seconds
                            $lunchBreakSeconds = Carbon::parse($schedule['lunchBreak'])->diffInSeconds(Carbon::parse('00:00:00'));
                            $breakTimeSeconds = Carbon::parse($schedule['breakTime'])->diffInSeconds(Carbon::parse('00:00:00'));

                            $workedSeconds -= ($lunchBreakSeconds + $breakTimeSeconds);

                            // Add to total
                            $totalWorkedSeconds += $workedSeconds;
                        }

                        // Subtract lunch duration if it exists
                        // Calculate total scheduled minutes for this day
                        $scheduledMinutes = ($schedule['clock_hours']['hours'] * 60) + $schedule['clock_hours']['minutes'];

                        // Subtract lunch duration if it exists
                        if ($schedule['lunch_duration'] && $schedule['lunch_duration'] != 'None') {
                            $lunchDuration = explode(' ', $schedule['lunch_duration']);
                            if ($lunchDuration[1] == 'Mins') {
                                $lunchMinutes = (int) $lunchDuration[0];
                            } elseif ($lunchDuration[1] == 'Hours') {
                                $lunchMinutes = (int) $lunchDuration[0] * 60;
                            } else {
                                $lunchMinutes = 0;
                            }
                        } else {
                            $lunchMinutes = 0;
                        }

                        // Subtract the lunch minutes from the scheduled minutes
                        $subtractedMinutes = max(0, $scheduledMinutes - $lunchMinutes);

                        // Add to the weekly total subtracted minutes
                        $totalSubtractedSchedulesMinutes += $subtractedMinutes;

                    }
                }

                // Convert total seconds to hours, minutes, and seconds
                $weeklyTotal['totalWorkedHours'] = floor($totalWorkedSeconds / 3600);
                $weeklyTotal['totalWorkedMinutes'] = floor(($totalWorkedSeconds % 3600) / 60);
                $weeklyTotal['weeklyWorkedHours'] = gmdate('H:i:s', $totalWorkedSeconds);
                // $weeklyTotal['totalSubtractedSchedulesMinutes'] = $totalSubtractedSchedulesMinutes;
                // $weeklyTotal['totalSubtractedSchedulesMinutes'] = gmdate("H:i:s", $totalSubtractedSchedulesMinutes * 60);
                $weeklyTotal['totalSubtractedSchedulesHours'] = $this->hoursformat($totalSubtractedSchedulesMinutes * 60);
            }
        }

        // print_r($formattedDataArray);
        return $formattedDataArray;
    }

    public function compareDateTime($datetime1, $datetime2)
    {
        // Parse the datetime strings using Carbon
        $date1 = Carbon::parse($datetime1);
        $date2 = Carbon::parse($datetime2);
        $date3 = $this->getTimeFromDateTime($date2);

        // Check if the datetimes are equal
        // if ($date1->eq($date2)) {
        //     return 'equal';
        // }

        // Check if $datetime1 is greater than $datetime2
        if ($date3 != '00:00:00') {
            if ($date1->gt($date2)) {
                return true;
            }
        }

        // Check if $datetime1 is less than $datetime2
        // if ($date1->lt($date2)) {
        //     return 'less';
        // }

        return false;
    }

    private function getTimeFromDateTime($datetime)
    {
        // Parse the datetime string using Carbon
        $date = Carbon::parse($datetime);

        // Extract the time part
        $time = $date->format('H:i:s'); // This will give you the time in 'HH:MM:SS' format

        return $time;
    }

    public function updateUserScheduleOld(UpdateUserScheduleRequest $request)
    {
        $findData = UserScheduleDetail::find($request->schedule_id);
        // return $findData;lunch_adjustment
        if (! empty($findData)) {
            $scheduleDate = Carbon::parse($findData->schedule_from);
            $scheduleDate = $scheduleDate->toDateString();
            $findData->schedule_from = $scheduleDate.' '.$request->schedule_from;
            $findData->schedule_to = $scheduleDate.' '.$request->schedule_to;
            $findData->lunch_duration = isset($request->lunch_duration) ? $request->lunch_duration : null;
            $findData->save();

            return response()->json([
                'ApiName' => 'updateUserSchedule',
                'Message' => "User's Schedule updated successfully",
                'status' => true,
                'data' => $findData,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => "User's Schedule not found",
                'status' => false,
                'data' => [],
            ], 400);
        }
    }

    public function updateUserSchedule(UpdateUserScheduleRequest $request)
    {
        // return $request->all();
        if ($request->has('schedule_id') && ! empty($request->schedule_id)) {
            $findData = UserScheduleDetail::find($request->schedule_id);
            $scheduleDate = Carbon::parse($findData->schedule_from);
            $scheduleDate = $scheduleDate->toDateString();
            $findData->schedule_from = $scheduleDate.' '.$request->schedule_from;
            $findData->schedule_to = $scheduleDate.' '.$request->schedule_to;
            $findData->lunch_duration = isset($request->lunch_duration) ? $request->lunch_duration : null;
            $findData->save();
        } else {
            DB::beginTransaction();
            $getUser = User::find($request->user_id);
            $userOfficeId = $getUser->office_id;
            $findUserScheduleData = UserSchedule::where('user_id', $request->user_id)->first();
            if (! empty($findUserScheduleData)) {
                $findUserScheduleId = $findUserScheduleData->id;
            } else {
                $findUserSchedule = new UserSchedule;
                $findUserSchedule->user_id = $request->user_id;
                $findUserSchedule->scheduled_by = $request->user_id;
                $findUserSchedule->save();
                $findUserScheduleId = $findUserSchedule->id;
            }
            $scheduleDate = $request->date;
            $schedule_from = $scheduleDate.' '.$request->schedule_from;
            $schedule_to = $scheduleDate.' '.$request->schedule_to;
            $checkUserScheduleDetailsData = UserScheduleDetail::where('schedule_id', $findUserScheduleId)
                ->where('office_id', $userOfficeId)
                ->whereDate('schedule_from', $scheduleDate)
                ->where('work_days', $request->day_no)
                ->exists();
            if ($checkUserScheduleDetailsData) {
                DB::rollBack();

                return response()->json(['message' => 'A schedule already exists, no data was inserted.'], 400);
            }
            $findData = new UserScheduleDetail;
            $findData->schedule_id = $findUserScheduleId;
            $findData->schedule_from = $schedule_from;
            $findData->schedule_to = $schedule_to;
            $findData->office_id = $userOfficeId;
            $findData->lunch_duration = $request->lunch_duration;
            $findData->work_days = $request->day_no;
            $findData->save();
        }
        // return $findData;lunch_adjustment
        if (! empty($findData)) {
            return response()->json([
                'ApiName' => 'updateUserSchedule',
                'Message' => "User's Schedule updated successfully",
                'status' => true,
                'data' => $findData,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => "User's Schedule not found",
                'status' => false,
                'data' => [],
            ], 400);
        }
    }

    public function calculateTotalHours($startDatetime, $endDatetime)
    {
        // $startDatetime = "2024-07-07 10:00:00";
        // $endDatetime =  "2024-07-07 19:00:00";
        // dd($startDatetime, $endDatetime);
        // Parse the start and end datetimes using Carbon
        if (! empty($startDatetime) && ! empty($endDatetime)) {
            $start = Carbon::parse($startDatetime);
            $end = Carbon::parse($endDatetime);

            // Calculate the difference in minutes
            $differenceInMinutes = $start->diffInMinutes($end);

            // Convert the difference to hours and minutes
            $hours = floor($differenceInMinutes / 60);
            $minutes = $differenceInMinutes % 60;

            // Return the result as an array
            return ['hours' => $hours, 'minutes' => $minutes];
        } else {
            return ['hours' => 0, 'minutes' => 0];
        }
    }

    public function updateUserScheduleRepeat(UpdateUserScheduleRepeatRequest $request)
    {
        // return $request->all();
        $user_id = $request->user_id;
        $is_repeat = $request->is_repeat;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $findUserSchedule = UserSchedule::where('user_id', $user_id)->first();
        $dateData = $this->currentWeekDate();
        // return $dateData;
        $currentWeekStart = $dateData['currentWeekStart'];
        $currentWeekEnd = $dateData['currentWeekEnd'];
        // return $findUserSchedule;
        if (! empty($findUserSchedule)) {
            $findUserSchedule->is_repeat = $is_repeat;
            $findUserSchedule->save();
            $schedule_id = $findUserSchedule->id;
            if ($findUserSchedule->is_repeat == 1) {
                // new code from here
                /*for ($i = 1; $i <= 12; $i++) {
                    $nextWeek = $this->getFutureWeekStartAndEndDates($i);
                    $nextWeekStartDate = $nextWeek['next_week_start_date'];
                    $nextWeekEndDate = $nextWeek['next_week_end_date'];

                    // Get current week's schedules for time reference
                    $pre_schedules = UserScheduleDetail::where('schedule_id', $schedule_id)
                        ->whereBetween(DB::raw('DATE(schedule_from)'), [$currentWeekStart, $currentWeekEnd])
                        ->get()->keyBy('work_days');
                    $existingSchedules = UserScheduleDetail::where('schedule_id', $schedule_id)
                        ->whereBetween(DB::raw('DATE(schedule_from)'), [$nextWeekStartDate, $nextWeekEndDate])
                        ->get()->keyBy('work_days');
                    // return $existingSchedules;
                    // return [$nextWeekStartDate, $nextWeekEndDate];
                    if(!$existingSchedules->isEmpty()){
                        // dd(2);
                        $scheduleCreatedArray = [];
                        foreach ($existingSchedules as $workDay => $existingSchedule) {
                            // Check if there is a corresponding schedule in the current week
                            if ($pre_schedules->has($workDay)) {
                                $currentSchedule = $pre_schedules[$workDay];

                                // Extract the time part from current week's schedule
                                $newTimeFrom = Carbon::parse($currentSchedule['schedule_from'])->format('H:i:s');
                                $newTimeTo = Carbon::parse($currentSchedule['schedule_to'])->format('H:i:s');
                                $lunch_duration = $currentSchedule['lunch_duration'];
                                $is_flexible = $currentSchedule['is_flexible'];
                                // Keep the date from the existing week's schedule and append the new time
                                $newScheduleFrom = Carbon::parse($existingSchedule['schedule_from'])->format('Y-m-d') . ' ' . $newTimeFrom;
                                $newScheduleTo = Carbon::parse($existingSchedule['schedule_to'])->format('Y-m-d') . ' ' . $newTimeTo;

                                // Update the existing schedule with the new time while keeping the date intact
                                $updatedarray = [
                                        'schedule_from' => $newScheduleFrom,
                                        'schedule_to' => $newScheduleTo,
                                        'lunch_duration' => $lunch_duration,
                                        'is_flexible' => $is_flexible,
                                        'updated_at' => Carbon::now(),
                                ];
                                //dd($updatedarray);
                                $updateSchedule =  $existingSchedule->update([
                                    'schedule_from' => $newScheduleFrom,
                                    'schedule_to' => $newScheduleTo,
                                    'lunch_duration' => $lunch_duration,
                                    'is_flexible' => $is_flexible,
                                    'updated_at' => Carbon::now(),
                                ]);
                            }
                            // if($updateSchedule){
                            //     $res = true;
                            // }
                            $res = true;
                        }

                    }else{

                        $pre_schedules = UserScheduleDetail::where('schedule_id', $schedule_id)
                        ->whereBetween(DB::raw('DATE(schedule_from)'), [$currentWeekStart, $currentWeekEnd])
                        ->get();
                        // return $pre_schedules;
                       if (!empty($pre_schedules) && count($pre_schedules) > 0) {
                            foreach ($pre_schedules as $schedule) {
                                // return $schedule;
                                $newScheduleFrom = Carbon::parse($schedule->schedule_from)
                                    ->addWeeks($i)
                                    ->toDateTimeString();
                                $newScheduleTo = Carbon::parse($schedule->schedule_to)
                                    ->addWeeks($i)
                                    ->toDateTimeString();
                                //dd($newScheduleFrom, $newScheduleTo);
                                $scheduleCreatedArray[] =[
                                    "schedule_id" => $schedule->schedule_id,
                                    "office_id" => $schedule->office_id,
                                    "schedule_from" => $newScheduleFrom,
                                    "schedule_to" => $newScheduleTo,
                                    "lunch_duration" => $schedule->lunch_duration,
                                    "work_days" => $schedule->work_days,
                                    "repeated_batch" => $schedule->repeated_batch,
                                    "updated_by" => $schedule->updated_by,
                                    "updated_type" => $schedule->updated_type,
                                    "user_attendance_id" => null,
                                    "attendance_status" => 0,
                                    "is_flexible" => $schedule->is_flexible,
                                    "created_at" => Carbon::now(),
                                    "updated_at" => Carbon::now(),
                                ];
                            }
                        }

                    }

                }
                //return $scheduleCreatedArray;
                if (isset($scheduleCreatedArray) && !empty($scheduleCreatedArray)) {
                    usort($scheduleCreatedArray, function ($a, $b) {
                        return strtotime($a['schedule_from']) - strtotime($b['schedule_from']);
                    });
                    $createdSchedules = UserScheduleDetail::insert($scheduleCreatedArray);
                    if($createdSchedules){
                        $res = true;
                    }
                }*/
                $dataForPusher = ['user_id' => Auth::user()->id];
                $res = GenerateUserSchedulesRepeatJob::dispatch($user_id, $is_repeat, $dataForPusher);
                // dd($res);

                if ($res) {
                    return response()->json([
                        'ApiName' => 'updateUserScheduleRepeat',
                        'Message' => "User's Schedule Repeat status is updated and next 3 months schedules are created",
                        'status' => true,
                        'data' => $findUserSchedule,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'updateUserScheduleRepeat',
                        'Message' => "User's Schedule Repeat status is updated and next 3 months schedules are already exists",
                        'status' => true,
                        'data' => $findUserSchedule,
                    ], 200);
                }

            } else {
                $dataForPusher = ['user_id' => Auth::user()->id];
                $res = DeleteUserSchedulesRepeatJob::dispatch($user_id, $is_repeat, $dataForPusher);

                return response()->json([
                    'ApiName' => 'updateUserScheduleRepeat',
                    'Message' => "User's Schedule Repeat status is updated and next 3 months schedules are removed",
                    'status' => true,
                    'data' => $findUserSchedule,
                ], 200);
            }

        } else {
            return response()->json([
                'ApiName' => 'updateUserScheduleRepeat',
                'Message' => "User's Schedule not found",
                'status' => false,
            ], 400);
        }

    }

    public function getSumWorkedHours($time)
    {
        $time1 = Carbon::createFromFormat('H:i:s', $time);
        $totalSeconds = $time1->secondsSinceMidnight();
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return ['hours' => $hours, 'minutes' => $minutes, 'seconds' => $seconds];
    }

    public function convertToTimeFormat($hours, $minutes, $seconds)
    {
        // Normalize the time values
        $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
        $normalizedHours = floor($totalSeconds / 3600);
        $totalSeconds %= 3600;
        $normalizedMinutes = floor($totalSeconds / 60);
        $normalizedSeconds = $totalSeconds % 60;

        // Create a Carbon instance and format it to H:i:s
        if ($hours >= 100) {
            $formattedTime = sprintf('%03d:%02d:%02d', $normalizedHours, $normalizedMinutes, $normalizedSeconds);
        } else {
            $formattedTime = sprintf('%02d:%02d:%02d', $normalizedHours, $normalizedMinutes, $normalizedSeconds);
        }

        return $formattedTime;
        // $time = Carbon::createFromTime($normalizedHours, $normalizedMinutes, $normalizedSeconds);
        // return $time->format('H:i:s');
    }

    private function convertToTimeFormatWeekly($hours, $minutes, $seconds = 0)
    {
        $totalMinutes = ($hours * 60) + $minutes + ($seconds / 60);
        $formattedHours = floor($totalMinutes / 60);
        $formattedMinutes = $totalMinutes % 60;

        return sprintf('%02d:%02d:%02d', $formattedHours, $formattedMinutes, $seconds);
    }

    private function weeklyWorkedHourse($user_id, $startWeek, $endWeek)
    {
        // dd($user_id, $startWeek, $endWeek);
        $user_attendence = UserAttendance::where('user_id', $user_id)
            ->whereBetween('date', [$startWeek, $endWeek])
            ->get();
        // echo '====> '.count($user_attendence);
        // echo '<pre>';print_r($user_attendence);
        // echo "#####  ".$startWeek."###### ".$endWeek;
        $sumHours = 0;
        $sumMinutes = 0;
        $sumSeconds = 0;
        $calculatedWorkedHours = '00:00:00';
        $current_time = '00:00:00';
        if (! empty($user_attendence)) {
            foreach ($user_attendence as $attendence) {
                // echo '<pre>';print_r($attendence);
                $current_time = $attendence->current_time ?? '00:00:00';
                $getSumWorkedHours = $this->getSumWorkedHours($current_time);
                // echo '<pre>'; print_r($getSumWorkedHours);
                $sumHours += $getSumWorkedHours['hours'];
                $sumMinutes += $getSumWorkedHours['minutes'];
                $sumSeconds += $getSumWorkedHours['seconds'];
                // echo $sumHours.'----'.$sumMinutes.'-----'.$sumSeconds;
                // $calculatedWorkedHours  = $this->convertToTimeFormat($sumHours, $sumMinutes, $sumSeconds);
                // echo '=====>  '.$calculatedWorkedHours.'<\n>';

                // $formattedData[$schedule->user_id]['workedHours'] =  $calculatedWorkedHours ?? "00:00:00";
            }
        }
        $calculatedWorkedHours = $this->convertToTimeFormat($sumHours, $sumMinutes, $sumSeconds);

        return $calculatedWorkedHours;

    }

    public function getUserAttendenceDetails(UserAttendenceDetailsRequest $request): JsonResponse
    {
        $data = [];
        $get_user_attendence = UserAttendance::where('user_id', $request->user_id)->where('date', $request->adjustment_date)->first();
        if (! empty($get_user_attendence)) {
            $user_checkin = UserAttendanceDetail::where('user_attendance_id', $get_user_attendence->id)
                ->whereDate('attendance_date', $get_user_attendence->date)
                ->where('type', 'clock in')
                ->first();
            $user_checkout = UserAttendanceDetail::where('user_attendance_id', $get_user_attendence->id)
                ->whereDate('attendance_date', $get_user_attendence->date)
                ->where('type', 'clock out')
                ->first();
            $user_attendence_id = ! empty($get_user_attendence) ? $get_user_attendence->id : null;
            if ($user_checkin) {
                $usercheckin = ! empty($user_checkin) ? $user_checkin->attendance_date : null;
                $usercheckout = ! empty($user_checkout) ? $user_checkout->attendance_date : null;
                $lunch_time = ! empty($get_user_attendence) ? $get_user_attendence->lunch_time : null;
                $break_time = ! empty($get_user_attendence) ? $get_user_attendence->break_time : null;
                $current_time = ! empty($get_user_attendence) ? $get_user_attendence->current_time : null;
                $adjustment_id = 0;
            } else {
                $get_request = ApprovalsAndRequest::where('user_id', $request->user_id)
                    ->where('adjustment_date', '=', $request->adjustment_date)
                    ->where('adjustment_type_id', 9)
                    ->first();
                if ($get_request) {
                    $adjustment_id = isset($get_request) ? $get_request->id : null;
                    $usercheckin = isset($get_request) ? $get_request->clock_in : null;
                    $usercheckout = isset($get_request) ? $get_request->clock_out : null;
                    $lunch_time = isset($get_request) ? $get_request->lunch_adjustment : null;
                    $break_time = isset($get_request) ? $get_request->break_adjustment : null;
                    $timein = new Carbon($usercheckin);
                    $timeout = new Carbon($usercheckout);
                    $totalHoursWorkedsec = $timein->diffInSeconds($timeout);

                    if ($lunch_time > 0) {
                        $lunchsecond = $lunch_time * 60;
                        $lunch_time = $this->hoursformat($lunchsecond);
                        $totalHoursWorkedsec -= $lunchsecond;

                    }
                    if ($break_time > 0) {
                        $breaksecond = $break_time * 60;
                        $break_time = $this->hoursformat($breaksecond);
                        $totalHoursWorkedsec -= $breaksecond;
                    }
                    $current_time = $this->hoursformat($totalHoursWorkedsec);
                    // $current_time = $this->hoursformat($totalHoursWorkedsec);
                }
            }
            $get_pto_request = ApprovalsAndRequest::where('user_id', $request->user_id)
                ->where('start_date', '<=', $request->adjustment_date)
                ->where('end_date', '>=', $request->adjustment_date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();
            if (! empty($get_pto_request) && isset($get_pto_request->pto_hours_perday)) {
                $pto_hours = $get_pto_request->pto_hours_perday;
            } else {
                $pto_hours = 0;
            }

            $data = [
                'user_attendence_id' => $user_attendence_id ?? null,
                'adjustment_id' => $adjustment_id ?? null,
                'clock_in' => $usercheckin ?? null,
                'clock_out' => $usercheckout ?? null,
                'lunch' => $lunch_time ?? null,
                'break' => $break_time ?? null,
                'total_worked_hours' => $current_time ?? null,
                'pto_hours' => $pto_hours,
            ];

            return response()->json([
                'ApiName' => 'getUserAttendenceDetails',
                'status' => true,
                'data' => $data,
            ], 200);
        } else {
            $get_request = ApprovalsAndRequest::where('user_id', $request->user_id)
                ->whereDate('adjustment_date', '=', $request->adjustment_date)
                ->where('adjustment_type_id', 9)
                ->first();
            if ($get_request) {
                $adjustment_id = isset($get_request) ? $get_request->id : null;
                $usercheckin = isset($get_request) ? $get_request->clock_in : null;
                $usercheckout = isset($get_request) ? $get_request->clock_out : null;
                $lunch_time = isset($get_request) ? $get_request->lunch_adjustment : null;
                $break_time = isset($get_request) ? $get_request->break_adjustment : null;
                $timein = new Carbon($usercheckin);
                $timeout = new Carbon($usercheckout);
                $totalHoursWorkedsec = $timein->diffInSeconds($timeout);

                if ($lunch_time > 0) {
                    $lunchsecond = $lunch_time * 60;
                    $lunch_time = $this->hoursformat($lunchsecond);
                    $totalHoursWorkedsec -= $lunchsecond;

                }
                if ($break_time > 0) {
                    $breaksecond = $break_time * 60;
                    $break_time = $this->hoursformat($breaksecond);
                    $totalHoursWorkedsec -= $breaksecond;
                }
                $current_time = $this->hoursformat($totalHoursWorkedsec);
                // $current_time = $this->hoursformat($totalHoursWorkedsec);
                $get_pto_request = ApprovalsAndRequest::where('user_id', $request->user_id)
                    ->where('start_date', '<=', $request->adjustment_date)
                    ->where('end_date', '>=', $request->adjustment_date)
                    ->where('adjustment_type_id', 8)
                    ->where('status', 'Approved')
                    ->first();
                if (! empty($get_pto_request) && isset($get_pto_request->pto_hours_perday)) {
                    $pto_hours = $get_pto_request->pto_hours_perday;
                } else {
                    $pto_hours = 0;
                }
                $data = [
                    'user_attendence_id' => null,
                    'adjustment_id' => $adjustment_id ?? null,
                    'clock_in' => $usercheckin ?? null,
                    'clock_out' => $usercheckout ?? null,
                    'lunch' => $lunch_time ?? null,
                    'break' => $break_time ?? null,
                    'total_worked_hours' => $current_time ?? null,
                    'pto_hours' => $pto_hours,
                ];
            }
            $get_pto_request = ApprovalsAndRequest::where('user_id', $request->user_id)
                ->where('start_date', '<=', $request->adjustment_date)
                ->where('end_date', '>=', $request->adjustment_date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();
            if (! empty($get_pto_request) && isset($get_pto_request->pto_hours_perday)) {
                $pto_hours = $get_pto_request->pto_hours_perday;
            } else {
                $pto_hours = 0;
            }
            $data = [
                'user_attendence_id' => null,
                'adjustment_id' => $adjustment_id ?? null,
                'clock_in' => $usercheckin ?? null,
                'clock_out' => $usercheckout ?? null,
                'lunch' => $lunch_time ?? null,
                'break' => $break_time ?? null,
                'total_worked_hours' => $current_time ?? null,
                'pto_hours' => $pto_hours,
            ];

            return response()->json([
                'ApiName' => 'getUserAttendenceDetails',
                // 'Message' => "User was not present",
                'Message' => "User's attendane data not found",
                'status' => true,
                'data' => $data,
            ], 200);
        }
    }

    protected function hoursformat($seconds)
    {
        $thours = intdiv($seconds, 3600); // Get the hours part
        $tminutes = intdiv($seconds % 3600, 60); // Get the minutes part
        $tseconds = $seconds % 60; // Get the remaining seconds part

        return sprintf('%02d:%02d:%02d', $thours, $tminutes, $tseconds);
    }

    public function userAttendenceAdjusmentOld(UserAttendenceAdjustmentRequest $request)
    {
        // return 'ok';
        $obj = new ScheduleAuditLog;
        $obj->user_id = $request->user_id;
        $obj->user_schedule_details_id = $request->user_schedule_details_id;
        $obj->user_attendance_id = $request->user_attendance_id;
        $obj->adjustment_date = $request->adjustment_date;
        $obj->clock_in = $request->adjustment_date.' '.$request->clock_in;
        $obj->clock_out = $request->adjustment_date.' '.$request->clock_out;
        $obj->lunch_adjustment_time = $request->lunch_adjustment_time;
        $obj->break_adjustment_time = $request->break_adjustment_time;
        $res = $obj->save();
        if ($res) {
            // need to update adjustment_id in user_attentdence_details table
            return response()->json([
                'ApiName' => 'userAttendenceAdjusment',
                'status' => true,
                'message' => 'Adjustment is successfully stored',
                'data' => $obj,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'userAttendenceAdjusment',
                'status' => true,
                'message' => 'Something went wrong',
                'data' => [],
            ], 400);
        }
    }

    public function userAdjustmentRequest(ClockInClockOutRequest $request){
        // return $request->all();
        $Validator = Validator::make($request->all(),
            [
                'document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:2048',
                // 'logo'  => 'required|mimes:jpg,png,jpeg,gif,svg|max:2048',
            ]);

        if ($Validator->fails()) {
            return response()->json(['error'=>$Validator->errors()], 400);
        }
        $is_worker_absent = false;
        $is_timesheet_request = false;
        if($request->is_worker_absent == 1){
            $is_worker_absent = true;
        }
        if($request->is_timesheet_request == 1){
            $is_timesheet_request = true;
        }
        if(!$request->image == NULL)
        {
            $file = $request->file('image');
            if (isset($file) && $file != null && $file!='') {
            //s3 bucket
            $img_path =  time().$file->getClientOriginalName();
            $img_path = str_replace(' ', '_',$img_path);
            $awsPath = config('app.domain_name').'/'.'request-image/'.$img_path;
            s3_upload($awsPath,file_get_contents($file),false);
            //s3 bucket end
            }
            $image_path =  time().$file->getClientOriginalName();
            $ex = $file->getClientOriginalExtension();
            $destinationPath = 'request-image';
            $image_path =   $file->move($destinationPath,  $img_path);
        }else{
            $image_path ='';
        }

        $user_id    = Auth::user()->id;
        $user_data  = User::where('id',$user_id)->first();
        // return $user_data;
        if($user_data->is_super_admin || $user_data->is_manager){
            $requestStatus = "Approved";
        }else{
            $requestStatus = "Pending";
        }
        if(!empty($request->user_id)){
            $userID = $request->user_id;
            $userManager = $user_data = User::where('id',$userID)->first();
            $managerId = $userManager->manager_id;

        }else{
            $userID = $user_id;
        }
        $office_id = null;
        $get_user_data  = User::where('id',$userID)->first();
        if(!empty($get_user_data) && isset($get_user_data->office_id)){
            $office_id = $get_user_data->office_id;
        }

        if (in_array($request->adjustment_type_id, [9])) {
            $terminated = checkTerminateFlag($userID, $request->adjustment_date);
            if ($terminated && $terminated->is_terminate) {
                return response()->json([
                    'ApiName' => 'add-request',
                    'status'  => true,
                    'message' => 'User have been terminated'
                ], 400);
            }

            $dismissed = checkDismissFlag($userID, $request->adjustment_date);
            if ($dismissed && $dismissed->dismiss) {
                return response()->json([
                    'ApiName' => 'add-request',
                    'status'  => true,
                    'message' => 'User have been disabled'
                ], 400);
            }

            $contractEnded = checkContractEndFlag($userID, $request->adjustment_date);
            if ($contractEnded) {
                return response()->json([
                    'ApiName' => 'add-request',
                    'status'  => true,
                    'message' => 'Your contract has been ended'
                ], 400);
            }
        }

        $adjustementType = AdjustementType::where('id',$request->adjustment_type_id)->first();

        $approvalsAndRequest = ApprovalsAndRequest::where('adjustment_type_id',$adjustementType->id)->whereNotNull('req_no')->latest('id')->first();
        if($approvalsAndRequest){
            $approvalsAndRequest = preg_replace('/[A-Za-z]+/', '',$approvalsAndRequest->req_no);
        }
        if($adjustementType->id ==1){

            if (!empty($approvalsAndRequest)) {
                $req_no = 'PD' . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'PD' .  str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }

        }elseif($adjustementType->id ==2){

            if (!empty($approvalsAndRequest)) {
                $req_no = 'R' . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'R' .  str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }

        }elseif ($adjustementType->id ==3) {

            if (!empty($approvalsAndRequest)) {
                $req_no = 'B' . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'B' .  str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        }elseif ($adjustementType->id ==4) {

            if (!empty($approvalsAndRequest)) {
                $req_no = 'A' . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'A' .  str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        }elseif ($adjustementType->id ==5) {

            if (!empty($approvalsAndRequest)) {
                $req_no = 'FF' . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'FF' .  str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        }elseif ($adjustementType->id ==6) {

            if (!empty($approvalsAndRequest)) {
                $req_no = 'I' . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'I' .  str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        }elseif($adjustementType->id ==7){

            if (!empty($approvalsAndRequest)) {
                $req_no = 'L' . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'L' .  str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }

        }elseif($adjustementType->id ==8){

            if (!empty($approvalsAndRequest)) {
                $req_no = 'PT' . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'PT' .  str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }

        }elseif($adjustementType->id ==9){

            if (!empty($approvalsAndRequest)) {
                $req_no = 'TA' . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'TA' .  str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }

        }else{
            if (!empty($approvalsAndRequest)) {
                $req_no = 'O' . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'O' .  str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        }
        // echo $req_no;die;
        // return $request;

        if ($adjustementType->id == 7 || $adjustementType->id == 8) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            
            $userPosition = User::select('id', 'sub_position_id')->where('id',$request->user_id)->first();
            $subPositionId = $userPosition->sub_position_id;
            
            $spayFrequency = $this->payFrequency($startDate, $subPositionId, $userPosition->id);
            $epayFrequency = $this->payFrequency($endDate, $subPositionId, $userPosition->id);
            
            if ($spayFrequency->closed_status == 1 || $epayFrequency->closed_status == 1) {
                return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the pay period has already been closed'], 400);
               
            }else {

                if ($adjustementType->id == 8) {
                    $start = Carbon::parse($startDate);
                    $end = Carbon::parse($endDate);
                    $daysCount = $start->diffInDays($end) + 1;
                    $ptoHoursPerday = ($request->pto_hours_perday * $daysCount);
                }else {
                    $ptoHoursPerday = null;
                }
                $insertUpdate = [
                    'user_id' => $userID,
                    'manager_id' => isset($managerId)?$managerId:$user_data->manager_id,
                    'created_by' => $user_id,
                    'req_no' => $req_no,
                    'approved_by' => $request->approved_by,
                    'adjustment_type_id' => $request->adjustment_type_id,
                    'pay_period' => $request->pay_period,
                    'state_id' => $request->state_id,
                    'dispute_type' => $request->dispute_type,
                    "description" => $request->description,
                    "cost_tracking_id" => $request->cost_tracking_id,
                    "emi" => $request->emi,
                    "request_date" => $request->request_date,
                    "cost_date" => $request->cost_date,
                    "amount" => $request->amount,
                    "image" => $image_path,
                    // "status" => "Approved",
                    "status" => $requestStatus,
                    "start_date" => isset($request->start_date)? $request->start_date:null,
                    "end_date" => isset($request->end_date)? $request->end_date:null,
                    "pto_hours_perday" => isset($ptoHoursPerday)? $ptoHoursPerday:null,
                    "adjustment_date" => isset($request->adjustment_date)? $request->adjustment_date:null,
                    "clock_in" => isset($request->clock_in)? $request->clock_in:null,
                    "clock_out" => isset($request->clock_out)? $request->clock_out:null,
                    "lunch_adjustment" => isset($request->lunch_adjustment)? $request->lunch_adjustment:null,
                    "break_adjustment" => isset($request->break_adjustment)? $request->break_adjustment:null,
                    "user_worker_type" => isset($get_user_data->worker_type)? $get_user_data->worker_type:null,
                    "pay_frequency" => isset($epayFrequency->pay_frequency)? $epayFrequency->pay_frequency:null,
                ];
                if ($request->adjustment_type_id == 9) {
                    $adjustmentDate = $request->adjustment_date;
                    $leaveData = ApprovalsAndRequest::where(['user_id' => $userID, 'adjustment_type_id'=> 7])->where('start_date','<=',$adjustmentDate)->where('end_date','>=',$adjustmentDate)->first();
                    if ($leaveData) {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because this adjustment date has already been leave request'], 400);
                    }
                    else {
                        $approvalData = ApprovalsAndRequest::where('adjustment_type_id',$request->adjustment_type_id)->where(['user_id' => $userID, 'adjustment_date'=> $adjustmentDate])->first();
                        if ($approvalData) {
                            $insertUpdate['req_no'] = $approvalData->req_no;
                            $approvalData = ApprovalsAndRequest::find($approvalData->id);
                            $approvalData->update($insertUpdate);
                        }else {
                            ApprovalsAndRequest::create($insertUpdate);
                        } 
                    }
                }
                //$data =  ApprovalsAndRequest::create();
                // return $data;
                if($data && $data->status == "Approved"){
                        $this->requestApprovalComment($data,$request);
                }
            }
            
        }
        else {
            if($is_worker_absent && $is_timesheet_request){
                $clock_in = $request->adjustment_date." 00:00:00";
                $clock_out = $request->adjustment_date." 00:00:00";
                $lunch_adjustment = 0;
                $break_adjustment = 0;
                $this->createOrUpdateUserSchedules($request->user_id, $office_id,$clock_in,$clock_out,$request->adjustment_date,null);
                //dd(1111);
                //dd($userID,$request->adjustment_date);
                $user_attendence = UserAttendance::where('user_id', $userID)
                    ->where('date', $request->adjustment_date)
                    ->first();
                $userschedule = UserSchedule::where('user_id',$userID)->first();
                if(!empty($user_attendence)){
                    $user_attendence->is_present = 0;
                    $user_attendence->save();
                    $checkUserScheduleDetail = UserScheduleDetail::where('user_attendance_id',$user_attendence->id)->first();
                    if(!empty($checkUserScheduleDetail)){
                        $checkUserScheduleDetail->user_attendance_id = null;
                        $checkUserScheduleDetail->save();
                    }
                    // return   response()->json([
                    //     'ApiName' => 'userAttendenceAdjusment',
                    //     'status'  => true,
                    //     'message' => 'Successfully',
                    //     ], 200);
                }else{
                    // $attendence_obj = new UserAttendance();
                    // $attendence_obj->user_id = $userID;
                    // $attendence_obj->current_time = "00:00:00";
                    // $attendence_obj->lunch_time = "00:00:00";
                    // $attendence_obj->break_time = "00:00:00";
                    // $attendence_obj->is_synced = 0;
                    // $attendence_obj->date = $request->adjustment_date;
                    // $attendence_obj->status = 0;
                    // $attendence_obj->is_present = 0;
                    // $attendence_obj->save();

                    // return   response()->json([
                    //     'ApiName' => 'userAttendenceAdjusment',
                    //     'status'  => true,
                    //     'message' => 'Successfully',
                    //     ], 200);
                }
                $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id',$userschedule->id)
                        ->where('office_id',$office_id)
                        ->wheredate('schedule_from',$request->adjustment_date)
                        ->first();
                // return $checkUserScheduleDetail;
                if($checkUserScheduleDetail){
                    $checkUserScheduleDetail->where('schedule_id',$userschedule->id)->where('office_id',$office_id)->wheredate('schedule_from',$request->adjustment_date)->update(['is_worker_absent' => 1]);
                }
                $insertUpdate = [
                    'user_id' => $userID,
                    'manager_id' => isset($managerId)?$managerId:$user_data->manager_id,
                    'created_by' => $user_id,
                    'req_no' => $req_no,
                    'approved_by' => $request->approved_by,
                    'adjustment_type_id' => $request->adjustment_type_id,
                    'pay_period' => $request->pay_period,
                    'state_id' => $request->state_id,
                    'dispute_type' => $request->dispute_type,
                    "description" => $request->description,
                    "cost_tracking_id" => $request->cost_tracking_id,
                    "emi" => $request->emi,
                    "request_date" => $request->request_date,
                    "cost_date" => $request->cost_date,
                    "amount" => $request->amount,
                    "image" => $image_path,
                    //"status" => "Approved",
                    "status" => $requestStatus,
                    "start_date" => isset($request->start_date)? $request->start_date:null,
                    "end_date" => isset($request->end_date)? $request->end_date:null,
                    "pto_hours_perday" => isset($ptoHoursPerday)? $ptoHoursPerday:null,
                    "adjustment_date" => isset($request->adjustment_date)? $request->adjustment_date:null,
                    "clock_in" => $clock_in,
                    "clock_out" => $clock_out,
                    "lunch_adjustment" => $lunch_adjustment,
                    "break_adjustment" => $break_adjustment,
                ];
                if ($request->adjustment_type_id == 9) {
                    $adjustmentDate = $request->adjustment_date;
                    $leaveData = ApprovalsAndRequest::where(['user_id' => $userID, 'adjustment_type_id'=> 7])->where('start_date','<=',$adjustmentDate)->where('end_date','>=',$adjustmentDate)->first();
                    if ($leaveData) {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because this adjustment date has already been leave request'], 400);
                    }
                    else {
                        $approvalData = ApprovalsAndRequest::where('adjustment_type_id',$request->adjustment_type_id)->where(['user_id' => $userID, 'adjustment_date'=> $adjustmentDate])->first();
                        if ($approvalData) {
                            $insertUpdate['req_no'] = $approvalData->req_no;
                            ApprovalsAndRequest::where('id',$approvalData->id)->update($insertUpdate);
                            $data = ApprovalsAndRequest::find($approvalData->id);
                        }else {
                            $record = ApprovalsAndRequest::create($insertUpdate);
                            $data = ApprovalsAndRequest::find($record->id);
                        } 

                        if($data->status == "Approved"){
                            $user_checkin = isset($request->clock_in) ? $request->clock_in : null;
                            $user_checkout = isset($request->clock_out) ? $request->clock_out : null;
                            $clockIn = Carbon::parse($user_checkin);
                            
                            $clockOut = Carbon::parse($user_checkout);
                            

                            // Calculate the difference in seconds
                            $diffInSeconds = $clockIn->diffInSeconds($clockOut);

                            // Convert the difference to a 00:00:00 format
                            $timeDifference = gmdate('H:i:s', $diffInSeconds);
                            $lunchBreak = isset($request->lunch_adjustment) ? $request->lunch_adjustment : 0;
                            $breakTime = isset($request->break_adjustment) ? $request->break_adjustment : 0;
                            if(!is_null($lunchBreak)){
                                $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                            }
                            if(!is_null($breakTime)){
                                $breakTime = gmdate('H:i:s', $breakTime * 60);
                            }
                            // need to minus lunch and break time 
                            $lunchDuration = Carbon::parse($lunchBreak)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                            $breakDuration = Carbon::parse($breakTime)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                            $totalWorkedSeconds = $diffInSeconds - ($lunchDuration + $breakDuration);
                            $finalTimeDifference = gmdate('H:i:s', $totalWorkedSeconds);
                            $attendanceData = [];
                            $attendanceData['id'] = $data->id;
                            $attendanceData['user_id'] = $data->user_id;
                            $attendanceData['current_time'] = $finalTimeDifference;
                            $attendanceData['lunch_time'] = $lunchBreak;
                            $attendanceData['break_time'] = $breakTime;
                            $attendanceData['date'] = $request->adjustment_date;
                            if(!empty($attendanceData['current_time'])){
                                $s = $this->payroll_wages_create($attendanceData);
                                // dd($s);
                            }
                        }
                    }
                }
                // return $data;
                if($data){
                    if($data->status == "Approved"){
                        $this->requestApprovalComment($data,$request);
                    }
                    $this->approvedTimeAdjustment($data, $userID);
                    return   response()->json([
                        'ApiName' => 'userAttendenceAdjusment',
                        'status'  => true,
                        'message' => 'Successfully',
                        ], 200);
                }
            }elseif($is_worker_absent == true && $is_timesheet_request== false){
                $user_attendence = UserAttendance::where('user_id', $userID)
                    ->where('date', $request->adjustment_date)
                    ->first();
                if(!empty($user_attendence)){
                    $user_attendence->is_present = 0;
                    $user_attendence->save();
                }else{
                    // $attendence_obj = new UserAttendance();
                    // $attendence_obj->user_id = $userID;
                    // $attendence_obj->current_time = "00:00:00";
                    // $attendence_obj->lunch_time = "00:00:00";
                    // $attendence_obj->break_time = "00:00:00";
                    // $attendence_obj->is_synced = 0;
                    // $attendence_obj->date = $request->adjustment_date;
                    // $attendence_obj->status = 0;
                    // $attendence_obj->is_present = 0;
                    // $attendence_obj->save();
                }
                $userschedule = UserSchedule::where('user_id',$userID)->first();
                $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id',$userschedule->id)
                        ->where('office_id',$office_id)
                        ->wheredate('schedule_from',$request->adjustment_date)
                        ->first();
                // return $checkUserScheduleDetail;
                if($checkUserScheduleDetail){
                    $checkUserScheduleDetail->where('schedule_id',$userschedule->id)->where('office_id',$office_id)->wheredate('schedule_from',$request->adjustment_date)->update(['is_worker_absent' => 1]);
                }
                $clock_in = $request->adjustment_date." 00:00:00";
                $clock_out = $request->adjustment_date." 00:00:00";
                $lunch_adjustment = 0;
                $break_adjustment = 0;
                $this->createOrUpdateUserSchedules($request->user_id, $office_id,$clock_in,$clock_out,$request->adjustment_date,null);
                $insertUpdate = [
                    'user_id' => $userID,
                    'manager_id' => isset($managerId)?$managerId:$user_data->manager_id,
                    'created_by' => $user_id,
                    'req_no' => $req_no,
                    'approved_by' => $request->approved_by,
                    'adjustment_type_id' => $request->adjustment_type_id,
                    'pay_period' => $request->pay_period,
                    'state_id' => $request->state_id,
                    'dispute_type' => $request->dispute_type,
                    "description" => $request->description,
                    "cost_tracking_id" => $request->cost_tracking_id,
                    "emi" => $request->emi,
                    "request_date" => $request->request_date,
                    "cost_date" => $request->cost_date,
                    "amount" => $request->amount,
                    "image" => $image_path,
                    //"status" => "Approved",
                    "status" => $requestStatus,
                    "start_date" => isset($request->start_date)? $request->start_date:null,
                    "end_date" => isset($request->end_date)? $request->end_date:null,
                    "pto_hours_perday" => isset($ptoHoursPerday)? $ptoHoursPerday:null,
                    "adjustment_date" => isset($request->adjustment_date)? $request->adjustment_date:null,
                    "clock_in" => $clock_in,
                    "clock_out" => $clock_out,
                    "lunch_adjustment" => $lunch_adjustment,
                    "break_adjustment" => $break_adjustment,
                ];
                if ($request->adjustment_type_id == 9) {
                    $adjustmentDate = $request->adjustment_date;
                    $leaveData = ApprovalsAndRequest::where(['user_id' => $userID, 'adjustment_type_id'=> 7])->where('start_date','<=',$adjustmentDate)->where('end_date','>=',$adjustmentDate)->first();
                    if ($leaveData) {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because this adjustment date has already been leave request'], 400);
                    }
                    else {
                        $approvalData = ApprovalsAndRequest::where('adjustment_type_id',$request->adjustment_type_id)->where(['user_id' => $userID, 'adjustment_date'=> $adjustmentDate])->first();
                        if ($approvalData) {
                            $insertUpdate['req_no'] = $approvalData->req_no;
                            ApprovalsAndRequest::where('id',$approvalData->id)->update($insertUpdate);
                            $data = ApprovalsAndRequest::find($approvalData->id);
                        }else {
                            $record = ApprovalsAndRequest::create($insertUpdate);
                            $data = ApprovalsAndRequest::find($record->id);
                        } 

                        if($data->status == "Approved"){
                            $user_checkin = isset($request->clock_in) ? $request->clock_in : null;
                            $user_checkout = isset($request->clock_out) ? $request->clock_out : null;
                            $clockIn = Carbon::parse($user_checkin);
                            
                            $clockOut = Carbon::parse($user_checkout);
                            

                            // Calculate the difference in seconds
                            $diffInSeconds = $clockIn->diffInSeconds($clockOut);

                            // Convert the difference to a 00:00:00 format
                            $timeDifference = gmdate('H:i:s', $diffInSeconds);
                            $lunchBreak = isset($request->lunch_adjustment) ? $request->lunch_adjustment : 0;
                            $breakTime = isset($request->break_adjustment) ? $request->break_adjustment : 0;
                            if(!is_null($lunchBreak)){
                                $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                            }
                            if(!is_null($breakTime)){
                                $breakTime = gmdate('H:i:s', $breakTime * 60);
                            }
                            // need to minus lunch and break time 
                            $lunchDuration = Carbon::parse($lunchBreak)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                            $breakDuration = Carbon::parse($breakTime)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                            $totalWorkedSeconds = $diffInSeconds - ($lunchDuration + $breakDuration);
                            $finalTimeDifference = gmdate('H:i:s', $totalWorkedSeconds);
                            $attendanceData = [];
                            $attendanceData['id'] = $data->id;
                            $attendanceData['user_id'] = $data->user_id;
                            $attendanceData['current_time'] = $finalTimeDifference;
                            $attendanceData['lunch_time'] = $lunchBreak;
                            $attendanceData['break_time'] = $breakTime;
                            $attendanceData['date'] = $request->adjustment_date;
                            if(!empty($attendanceData['current_time'])){
                                $s = $this->payroll_wages_create($attendanceData);
                                // dd($s);
                            }
                        }
                    }
                }
                // return $data;
                if($data){
                    if($data->status == "Approved"){
                        $this->requestApprovalComment($data,$request);
                    }
                    $this->approvedTimeAdjustment($data, $userID);
                    return   response()->json([
                        'ApiName' => 'userAttendenceAdjusment',
                        'status'  => true,
                        'message' => 'Successfully',
                        ], 200);
                }
            }else{
                $clock_in = isset($request->clock_in) ? $request->clock_in : null;
                $clock_out = isset($request->clock_out) ? $request->clock_out : null;
                $lunch_adjustment = isset($request->lunch_adjustment) ? $request->lunch_adjustment : null;
                $break_adjustment = isset($request->break_adjustment) ? $request->break_adjustment : null;
                $this->createOrUpdateUserSchedules($request->user_id, $office_id,$clock_in,$clock_out,$request->adjustment_date,$lunch_adjustment);
                $userschedule = UserSchedule::where('user_id',$userID)->first();
                $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id',$userschedule->id)
                        ->where('office_id',$office_id)
                        ->wheredate('schedule_from',$request->adjustment_date)
                        ->first();
                // return $checkUserScheduleDetail;
                if($checkUserScheduleDetail){
                    $checkUserScheduleDetail->where('schedule_id',$userschedule->id)->where('office_id',$office_id)->wheredate('schedule_from',$request->adjustment_date)->update(['is_worker_absent' => 0]);
                }
            }
            $insertUpdate = [
                'user_id' => $userID,
                'manager_id' => isset($managerId)?$managerId:$user_data->manager_id,
                'created_by' => $user_id,
                'req_no' => $req_no,
                'approved_by' => $request->approved_by,
                'adjustment_type_id' => $request->adjustment_type_id,
                'pay_period' => $request->pay_period,
                'state_id' => $request->state_id,
                'dispute_type' => $request->dispute_type,
                "description" => $request->description,
                "cost_tracking_id" => $request->cost_tracking_id,
                "emi" => $request->emi,
                "request_date" => $request->request_date,
                "cost_date" => $request->cost_date,
                "amount" => $request->amount,
                "image" => $image_path,
                //"status" => "Approved",
                "status" => $requestStatus,
                "start_date" => isset($request->start_date)? $request->start_date:null,
                "end_date" => isset($request->end_date)? $request->end_date:null,
                "pto_hours_perday" => isset($ptoHoursPerday)? $ptoHoursPerday:null,
                "adjustment_date" => isset($request->adjustment_date)? $request->adjustment_date:null,
                "clock_in" => isset($request->clock_in)? $request->clock_in:null,
                "clock_out" => isset($request->clock_out)? $request->clock_out:null,
                "lunch_adjustment" => isset($request->lunch_adjustment)? $request->lunch_adjustment:null,
                "break_adjustment" => isset($request->break_adjustment)? $request->break_adjustment:null,
            ];
            if ($request->adjustment_type_id == 9) {
                $adjustmentDate = $request->adjustment_date;
                $leaveData = ApprovalsAndRequest::where(['user_id' => $userID, 'adjustment_type_id'=> 7])->where('start_date','<=',$adjustmentDate)->where('end_date','>=',$adjustmentDate)->first();
                if ($leaveData) {
                    return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because this adjustment date has already been leave request'], 400);
                }
                else {
                    $approvalData = ApprovalsAndRequest::where('adjustment_type_id',$request->adjustment_type_id)->where(['user_id' => $userID, 'adjustment_date'=> $adjustmentDate])->first();
                    if ($approvalData) {
                        $insertUpdate['req_no'] = $approvalData->req_no;
                        ApprovalsAndRequest::where('id',$approvalData->id)->update($insertUpdate);
                        $data = ApprovalsAndRequest::find($approvalData->id);
                    }else {
                        $record = ApprovalsAndRequest::create($insertUpdate);
                        $data = ApprovalsAndRequest::find($record->id);
                    } 

                    if($data->status == "Approved"){
                        $user_checkin = isset($request->clock_in) ? $request->clock_in : null;
                        $user_checkout = isset($request->clock_out) ? $request->clock_out : null;
                        $clockIn = Carbon::parse($user_checkin);
                        
                        $clockOut = Carbon::parse($user_checkout);
                        

                        // Calculate the difference in seconds
                        $diffInSeconds = $clockIn->diffInSeconds($clockOut);

                        // Convert the difference to a 00:00:00 format
                        $timeDifference = gmdate('H:i:s', $diffInSeconds);
                        $lunchBreak = isset($request->lunch_adjustment) ? $request->lunch_adjustment : 0;
                        $breakTime = isset($request->break_adjustment) ? $request->break_adjustment : 0;
                        if(!is_null($lunchBreak)){
                            $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                        }
                        if(!is_null($breakTime)){
                            $breakTime = gmdate('H:i:s', $breakTime * 60);
                        }
                        // need to minus lunch and break time 
                        $lunchDuration = Carbon::parse($lunchBreak)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                        $breakDuration = Carbon::parse($breakTime)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                        $totalWorkedSeconds = $diffInSeconds - ($lunchDuration + $breakDuration);
                        $finalTimeDifference = gmdate('H:i:s', $totalWorkedSeconds);
                        $attendanceData = [];
                        $attendanceData['id'] = $data->id;
                        $attendanceData['user_id'] = $data->user_id;
                        $attendanceData['current_time'] = $finalTimeDifference;
                        $attendanceData['lunch_time'] = $lunchBreak;
                        $attendanceData['break_time'] = $breakTime;
                        $attendanceData['date'] = $request->adjustment_date;
                        if(!empty($attendanceData['current_time'])){
                            $s = $this->payroll_wages_create($attendanceData);
                            // dd($s);
                        }
                    }
                }
            }
            // return $data;
            if($data){
                // if($data->status == "Approved"){
                //     $this->requestApprovalComment($data,$request);
                // }
                $this->requestApprovalComment($data,$request);
                $this->approvedTimeAdjustment($data, $userID);
            }
            
        }

        $customerPid = $request->customer_pid;
        if($customerPid)
        {
        //  $pid = implode(',',$customerPid);
            $valPid = explode(',',$customerPid);
            foreach($valPid as $val)
            {
                $customerName = SalesMaster::where('pid',$val)->first();
                RequestApprovelByPid::create([
                    'request_id' => $data->id,
                    'pid' => $val,
                    'customer_name' => isset($customerName->customer_name)?$customerName->customer_name:null,
                ]);
            }
        }

        if($user_data->manager_id){

            // $data =  Notification::create([
            //     'user_id' => isset($user_data->manager_id)?$user_data->manager_id:1,
            //     'type' => 'request-approval',
            //     'description' => 'A new request is generated by '.$user_data->first_name,
            //     'is_read' => 0,

            // ]);

            $notificationData = array(
                'user_id'      => isset($user_data->manager_id)?$user_data->manager_id:1,
                'device_token' => $user_data->device_token,
                'title'        => 'A new request is generated.',
                'sound'        => 'sound',
                'type'         => 'request-approval',
                'body'         => 'A new request is generated by '.$user_data->forst_name,
            );
            //$this->sendNotification($notificationData);
        }
        $user = array(

            'user_id'      => isset($user_data->manager_id)?$user_data->manager_id:1,
            'description' => 'A new request is generated by '.$user_data->first_name.' '.$user_data->last_name,
            'type'         => 'request-approval',
            'is_read' => 0,
        );
        $notify =  event(new UserloginNotification($user));

        return   response()->json([
            'ApiName' => 'userAttendenceAdjusment',
            'status'  => true,
            'message' => 'Successfully',
            ], 200);

    }

    public function requestApprovalComment($data, $request)
    {
        $data = ApprovalAndRequestComment::create([
            'user_id' => $data->user_id,
            'request_id' => $data->id,
            'type' => 'comment',
            'comment' => $request->comment,
            // "image" => $image_path,
        ]);
        if ($data) {
            return true;
        } else {
            return false;
        }
    }

    public function userAuditLogs(Request $request)
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        if (! empty($request->limit)) {
            $perpage = $request->limit;
        }
        $authUser = Auth::user();
        $authUserId = Auth::user()->id;
        if ($request->has('user_id') && ! empty($request->user_id)) {
            $getRequests = ApprovalsAndRequest::with('comments')->where('user_id', $request->user_id)
                ->whereIn('adjustment_type_id', [7, 8, 9])
                ->where('status', 'Approved');
            // ->paginate($perpage);
        } else {
            if ($authUser->is_manager) {
                $userIds = User::where('manager_id', $authUserId)->pluck('id');
                // dd($userIds);
                $getRequests = ApprovalsAndRequest::with('comments')
                    ->whereIn('user_id', $userIds)
                    ->whereIn('adjustment_type_id', [7, 8, 9])
                    ->where('status', 'Approved');
            } else {
                $getRequests = ApprovalsAndRequest::with('comments')
                        // ->where('user_id', $request->user_id)
                    ->whereIn('adjustment_type_id', [7, 8, 9])
                    ->where('status', 'Approved');
            }

            // ->paginate($perpage);
        }
        $getRequests = $getRequests->orderBy('created_at', 'desc');
        $getRequests = $getRequests->paginate($perpage);
        // return $getRequests;

        $data = [];
        if (! empty($getRequests)) {
            foreach ($getRequests as $k => $getRequest) {
                $adjustementType = AdjustementType::where('id', $getRequest->adjustment_type_id)->first();
                // $user_data  = User::where('id',$getRequest->manager_id)->first();
                $user_data = User::where('id', $getRequest->approved_by)->first();
                if ($getRequest->adjustment_type_id == 9) {
                    // adjustment
                    // echo '-----Adjustment-------------';
                    // echo '<pre>'; print_r($getRequest);
                    $user_checkin = null;
                    $user_checkout = null;
                    $user_attendence = UserAttendance::where('user_id', $getRequest->user_id)->where('date', $getRequest->adjustment_date)->first();
                    if (! empty($user_attendence)) {
                        // $user_checkin = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        //     ->whereDate('attendance_date',$user_attendence->date)
                        //     ->where('type','clock in')
                        //     ->first();
                        // $user_checkout = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        //             ->whereDate('attendance_date',$user_attendence->date)
                        //             ->where('type','clock out')
                        //             ->first();
                        // $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        //     ->whereDate('attendance_date',$user_attendence->date)
                        //     ->where('adjustment_id','>',0)
                        //     ->first();
                        // if($user_attendance_obj){
                        //     $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                        //     $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                        //     $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                        // }else{
                        //     $user_checkin_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        //         ->whereDate('attendance_date',$user_attendence->date)
                        //         ->where('type','clock in')
                        //         ->first();
                        //     $user_checkin = $user_checkin_obj->attendance_date;
                        //     $user_checkout_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        //         ->whereDate('attendance_date',$user_attendence->date)
                        //         ->where('type','clock out')
                        //         ->first();
                        //     $user_checkout = $user_checkout_obj->attendance_date;
                        // }

                        $user_checkin_obj = UserAttendanceDetail::withTrashed()->where('user_attendance_id', $user_attendence->id)
                            ->whereDate('attendance_date', $user_attendence->date)
                            ->where('type', 'clock in')
                            ->first();
                        if (! empty($user_checkin_obj)) {
                            $user_checkin = $user_checkin_obj->attendance_date;
                            $user_checkout_obj = UserAttendanceDetail::withTrashed()->where('user_attendance_id', $user_attendence->id)
                                ->whereDate('attendance_date', $user_attendence->date)
                                ->where('type', 'clock out')
                                ->first();
                            if (! empty($user_checkout_obj)) {
                                $user_checkout = $user_checkout_obj->attendance_date;
                            }
                        }
                    }
                    // $data[] = $this->formatResponse($getRequest,$get_user_attendence);
                    $data[] = [
                        // 'user_attendence_id' => !empty($get_user_attendence)? $get_user_attendence->id : null,
                        'request_id' => $getRequest->req_no,
                        'adjustment_type_id' => $getRequest->adjustment_type_id,
                        'adjustment_type' => ! empty($adjustementType) ? $adjustementType->name : null,
                        'origional_clock_in' => ! empty($user_checkin) ? $user_checkin : null,
                        'origional_clock_out' => ! empty($user_checkout) ? $user_checkout : null,
                        'origional_lunch' => ! empty($user_attendence) ? $user_attendence->lunch_time : null,
                        'origional_break' => ! empty($user_attendence) ? $user_attendence->break_time : null,
                        'total_worked_hours' => ! empty($user_attendence) ? $user_attendence->current_time : null,
                        'clock_in' => ! empty($getRequest) ? $getRequest->clock_in : null,
                        'clock_out' => ! empty($getRequest) ? $getRequest->clock_out : null,
                        'lunch' => ! empty($getRequest) ? $getRequest->lunch_adjustment : null,
                        'break' => ! empty($getRequest) ? $getRequest->break_adjustment : null,
                        'pto' => null,
                        'pto_date' => null,
                        'leave' => null,
                        'adjustment' => ! empty($getRequest->adjustment_date) ? $getRequest->adjustment_date : null,
                        'apprroved_by' => ! empty($user_data) ? $user_data->first_name.' '.$user_data->last_name : null,
                    ];
                    // return $res;
                } elseif ($getRequest->adjustment_type_id == 7) {
                    // Leave
                    // echo '-----Leave-------------';
                    // echo '<pre>'; print_r($getRequest);
                    if (! empty($getRequest->start_date) && ! empty($getRequest->end_date)) {
                        // echo '1========>';
                        $s_date = Carbon::parse($getRequest->start_date);
                        $e_date = Carbon::parse($getRequest->end_date);
                        $countDay = $s_date->diffInDays($e_date);
                        $leave_user_attendence = UserAttendance::where('user_id', $getRequest->user_id)
                            ->whereBetween('date', [$getRequest->start_date, $getRequest->end_date])
                            ->get();
                        // dump($leave_user_attendence);
                        // echo 'hello-----------'."\n";
                        // echo '<pre>'; print_r($leave_user_attendence);
                        if ($leave_user_attendence->isEmpty()) {
                            $data[] = [
                                // 'user_attendence_id' => !empty($get_user_attendence)? $get_user_attendence->id : null,
                                'request_id' => $getRequest->req_no,
                                'adjustment_type_id' => $getRequest->adjustment_type_id,
                                'adjustment_type' => ! empty($adjustementType) ? $adjustementType->name : null,
                                'origional_clock_in' => null,
                                'origional_clock_out' => null,
                                'origional_lunch' => null,
                                'origional_break' => null,
                                'total_worked_hours' => null,
                                'clock_in' => ! empty($getRequest) ? $getRequest->start_date : null,
                                'clock_out' => ! empty($getRequest) ? $getRequest->end_date : null,
                                'lunch' => null,
                                'break' => null,
                                'pto' => null,
                                'pto_date' => null,
                                'leave' => ! empty($getRequest) ? $getRequest->start_date : null,
                                'adjustment' => null,
                                'apprroved_by' => ! empty($user_data) ? $user_data->first_name.' '.$user_data->last_name : null,
                            ];
                        } else {
                            for ($date = $s_date; $date <= $e_date; $date->addDay()) {
                                $leave_user_attendence = UserAttendance::where('user_id', $getRequest->user_id)
                                    ->where('date', $date->toDateString())
                                    ->first();
                                // echo 'hello==================>';
                                // echo '<pre>'; print_r($user_attendence);echo "hhhhhhh \n";
                                // $res[$k] = $this->formatResponse($getRequest,$get_user_attendence);
                                if (! empty($leave_user_attendence)) {
                                    $leave_user_checkin = UserAttendanceDetail::where('user_attendance_id', $leave_user_attendence->id)
                                        ->whereDate('attendance_date', $leave_user_attendence->date)
                                        ->where('type', 'clock in')
                                        ->first();
                                    $leave_user_checkout = UserAttendanceDetail::where('user_attendance_id', $leave_user_attendence->id)
                                        ->whereDate('attendance_date', $leave_user_attendence->date)
                                        ->where('type', 'clock out')
                                        ->first();

                                    $data[] = [
                                        // 'user_attendence_id' => !empty($get_user_attendence)? $get_user_attendence->id : null,
                                        'request_id' => $getRequest->req_no,
                                        'adjustment_type_id' => $getRequest->adjustment_type_id,
                                        'adjustment_type' => ! empty($adjustementType) ? $adjustementType->name : null,
                                        'origional_clock_in' => ! empty($leave_user_checkin) ? $leave_user_checkin->attendance_date : null,
                                        'origional_clock_out' => ! empty($leave_user_checkout) ? $leave_user_checkout->attendance_date : null,
                                        'origional_lunch' => ! empty($leave_user_attendence) ? $leave_user_attendence->lunch_time : null,
                                        'origional_break' => ! empty($leave_user_attendence) ? $leave_user_attendence->break_time : null,
                                        'total_worked_hours' => ! empty($leave_user_attendence) ? $leave_user_attendence->current_time : null,
                                        'clock_in' => ! empty($getRequest) ? $getRequest->start_date : null,
                                        'clock_out' => ! empty($getRequest) ? $getRequest->end_date : null,
                                        'lunch' => null,
                                        'break' => null,
                                        'pto' => null,
                                        'pto_date' => null,
                                        'leave' => ! empty($date->toDateString()) ? $date->toDateString() : null,
                                        'adjustment' => null,
                                        'apprroved_by' => ! empty($user_data) ? $user_data->first_name.' '.$user_data->last_name : null,
                                    ];
                                } else {
                                    $data[] = [
                                        // 'user_attendence_id' => !empty($get_user_attendence)? $get_user_attendence->id : null,
                                        'request_id' => $getRequest->req_no,
                                        'adjustment_type_id' => $getRequest->adjustment_type_id,
                                        'adjustment_type' => ! empty($adjustementType) ? $adjustementType->name : null,
                                        'origional_clock_in' => null,
                                        'origional_clock_out' => null,
                                        'origional_lunch' => null,
                                        'origional_break' => null,
                                        'total_worked_hours' => null,
                                        'clock_in' => ! empty($getRequest) ? $getRequest->clock_in : null,
                                        'clock_out' => ! empty($getRequest) ? $getRequest->clock_out : null,
                                        'lunch' => ! empty($getRequest) ? $getRequest->lunch_adjustment : null,
                                        'break' => ! empty($getRequest) ? $getRequest->break_adjustment : null,
                                        'pto' => null,
                                        'pto_date' => null,
                                        'leave' => ! empty($date->toDateString()) ? $date->toDateString() : null,
                                        'adjustment' => null,
                                        'apprroved_by' => ! empty($user_data) ? $user_data->first_name.' '.$user_data->last_name : null,
                                    ];
                                }

                            }
                        }
                    }
                } elseif ($getRequest->adjustment_type_id == 8) {
                    // echo '-----PTO-------------';
                    // echo '<pre>'; print_r($getRequest);
                    if (! empty($getRequest->start_date) && ! empty($getRequest->end_date)) {
                        $s_date = Carbon::parse($getRequest->start_date);
                        $e_date = Carbon::parse($getRequest->end_date);
                        $countDay = $s_date->diffInDays($e_date);
                        $countDay = $countDay + 1;
                        $pto_user_attendence = UserAttendance::where('user_id', $getRequest->user_id)
                            ->whereBetween('date', [$getRequest->start_date, $getRequest->end_date])
                            ->get();
                        if ($pto_user_attendence->isEmpty()) {
                            $data[] = [
                                // 'user_attendence_id' => !empty($get_user_attendence)? $get_user_attendence->id : null,
                                'request_id' => $getRequest->req_no,
                                'adjustment_type_id' => $getRequest->adjustment_type_id,
                                'adjustment_type' => ! empty($adjustementType) ? $adjustementType->name : null,
                                'origional_clock_in' => null,
                                'origional_clock_out' => null,
                                'origional_lunch' => null,
                                'origional_break' => null,
                                'total_worked_hours' => null,
                                'clock_in' => ! empty($getRequest) ? $getRequest->start_date : null,
                                'clock_out' => ! empty($getRequest) ? $getRequest->end_date : null,
                                'lunch' => null,
                                'break' => null,
                                'pto' => ! empty($getRequest) ? ($getRequest->pto_hours_perday * $countDay) : null,
                                'pto_date' => ! empty($getRequest) ? $getRequest->start_date : null,
                                'apprroved_by' => ! empty($user_data) ? $user_data->first_name.' '.$user_data->last_name : null,
                            ];
                        } else {
                            for ($date = $s_date; $date <= $e_date; $date->addDay()) {
                                // $d = $s_date->addDays($i);
                                $pto_get_user_attendence = UserAttendance::where('user_id', $getRequest->user_id)
                                    ->where('date', $date->toDateString())
                                    ->first();
                                // $res[$k] = $this->formatResponse($get_user_attendence);
                                if (! empty($pto_get_user_attendence)) {
                                    $pto_user_checkin = UserAttendanceDetail::where('user_attendance_id', $pto_get_user_attendence->id)
                                        ->whereDate('attendance_date', $pto_get_user_attendence->date)
                                        ->where('type', 'clock in')
                                        ->first();
                                    $pto_user_checkout = UserAttendanceDetail::where('user_attendance_id', $pto_get_user_attendence->id)
                                        ->whereDate('attendance_date', $pto_get_user_attendence->date)
                                        ->where('type', 'clock out')
                                        ->first();
                                    $data[] = [
                                        // 'user_attendence_id' => !empty($get_user_attendence)? $get_user_attendence->id : null,
                                        'request_id' => $getRequest->req_no,
                                        'adjustment_type_id' => $getRequest->adjustment_type_id,
                                        'adjustment_type' => ! empty($adjustementType) ? $adjustementType->name : null,
                                        'origional_clock_in' => ! empty($pto_user_checkin) ? $pto_user_checkin->attendance_date : null,
                                        'origional_clock_out' => ! empty($pto_user_checkout) ? $pto_user_checkout->attendance_date : null,
                                        'origional_lunch' => ! empty($pto_get_user_attendence) ? $pto_get_user_attendence->lunch_time : null,
                                        'origional_break' => ! empty($pto_get_user_attendence) ? $pto_get_user_attendence->break_time : null,
                                        'total_worked_hours' => ! empty($pto_get_user_attendence) ? $pto_get_user_attendence->current_time : null,
                                        'clock_in' => ! empty($getRequest) ? $getRequest->start_date : null,
                                        'clock_out' => ! empty($getRequest) ? $getRequest->end_date : null,
                                        'lunch' => null,
                                        'break' => null,
                                        'pto' => ! empty($getRequest) ? $getRequest->pto_hours_perday : null,
                                        'pto_date' => ! empty($date->toDateString()) ? $date->toDateString() : null,
                                        'leave' => null,
                                        'adjustment' => null,
                                        'apprroved_by' => ! empty($user_data) ? $user_data->first_name.' '.$user_data->last_name : null,
                                    ];
                                } else {
                                    $data[] = [
                                        // 'user_attendence_id' => !empty($get_user_attendence)? $get_user_attendence->id : null,
                                        'request_id' => $getRequest->req_no,
                                        'adjustment_type_id' => $getRequest->adjustment_type_id,
                                        'adjustment_type' => ! empty($adjustementType) ? $adjustementType->name : null,
                                        'origional_clock_in' => null,
                                        'origional_clock_out' => null,
                                        'origional_lunch' => null,
                                        'origional_break' => null,
                                        'total_worked_hours' => null,
                                        'clock_in' => ! empty($getRequest) ? $getRequest->clock_in : null,
                                        'clock_out' => ! empty($getRequest) ? $getRequest->clock_out : null,
                                        'lunch' => ! empty($getRequest) ? $getRequest->lunch_adjustment : null,
                                        'break' => ! empty($getRequest) ? $getRequest->break_adjustment : null,
                                        'pto' => ! empty($getRequest) ? $getRequest->pto_hours_perday : null,
                                        'pto_date' => ! empty($date->toDateString()) ? $date->toDateString() : null,
                                        'leave' => null,
                                        'adjustment' => null,
                                        'apprroved_by' => ! empty($user_data) ? $user_data->first_name.' '.$user_data->last_name : null,
                                    ];
                                }
                            }
                        }
                    }
                }
            }

        }
        // return $data;
        // $data = $this->paginates($data,$perpage);
        $pdata['data'] = $data;
        $pdata['current_page'] = $getRequests->currentPage();
        $pdata['links'] = $getRequests->links();

        $pdata['last_page'] = $getRequests->lastPage();
        $pdata['last_page_url'] = $getRequests->url($getRequests->lastPage());
        $pdata['next_page_url'] = $getRequests->nextPageUrl();
        $pdata['path'] = $getRequests->path();
        $pdata['per_page'] = $getRequests->perPage();
        $pdata['prev_page_url'] = $getRequests->previousPageUrl();
        // $jobs['to'] = $jobandsales->to();
        $pdata['total'] = $getRequests->total();
        $data = $pdata;
        if ($request->has('user_id') && ! empty($request->user_id)) {
            $user_attendence_data = UserAttendance::where('user_id', $request->user_id)->get();
            $user_attendence_data_count = UserAttendance::where('user_id', $request->user_id)->count();
        } else {
            if ($authUser->is_manager) {
                $userIds = User::where('manager_id', $authUserId)->pluck('id');
                $user_attendence_data = UserAttendance::whereIn('user_id', $userIds)->get();
                $user_attendence_data_count = UserAttendance::whereIn('user_id', $userIds)->count();
            } else {
                $user_attendence_data = UserAttendance::get();
                $user_attendence_data_count = UserAttendance::count();
            }

        }

        // return [$user_attendence_data, $user_attendence_data_count];

        $totalCurrentTimeSeconds = 0;
        $totalBreakTimeSeconds = 0;
        $totalLunchTimeSeconds = 0;
        $totalCurrentTimeSeconds_1 = 0;
        $totalBreakTimeSeconds_1 = 0;
        $totalLunchTimeSeconds_1 = 0;

        $currentCount = 0;
        $breakCount = 0;
        $lunchCount = 0;
        $currentCount_1 = 0;
        $breakCount_1 = 0;
        $lunchCount_1 = 0;
        $arrayDate = [];

        foreach ($user_attendence_data as $entry) {
            // Sum current_time

            $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $entry->id)
                ->whereDate('attendance_date', $entry->date)
                ->where('adjustment_id', '>', 0)
                ->first();
            if ($user_attendance_obj) {
                array_push($arrayDate, $entry->date);
                $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                $user_checkin = isset($get_request) ? $get_request->clock_in : '00:00:00';
                $user_checkout = isset($get_request) ? $get_request->clock_out : '00:00:00';
                $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : null;
                $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : null;

                $checkin_time = strtotime($user_checkin);
                $checkout_time = strtotime($user_checkout);
                $totalCurrentTimeSeconds += $checkout_time - $checkin_time - ($breakTime * 60) - ($lunchBreak * 60);
                $currentCount++;
                // dump($totalCurrentTimeSeconds);

                if (! is_null($breakTime)) {
                    $totalBreakTimeSeconds += $breakTime * 60;
                    $breakCount++;
                }
                // dump($totalBreakTimeSeconds);
                if (! is_null($lunchBreak)) {
                    $totalLunchTimeSeconds += $lunchBreak * 60;
                    $lunchCount++;
                }
                // dump($totalLunchTimeSeconds);
            } else {
                // break time
                if (! empty($entry['current_time']) && $this->isValidExtendedTime($entry['current_time'])) {
                    $totalCurrentTimeSeconds += $this->convertTimeToSeconds($entry['current_time']);
                    $currentCount++;
                }
                // Sum break_time
                if (! empty($entry['break_time']) && $this->isValidExtendedTime($entry['break_time'])) {
                    $totalBreakTimeSeconds += $this->convertTimeToSeconds($entry['break_time']);
                    $breakCount++;
                }
                // Sum lunch_time
                if (! empty($entry['lunch_time']) && $this->isValidExtendedTime($entry['lunch_time'])) {
                    $totalLunchTimeSeconds += $this->convertTimeToSeconds($entry['lunch_time']);
                    $lunchCount++;
                }
            }

        }
        // dump($arrayDate);
        if ($request->has('user_id') && ! empty($request->user_id)) {
            $user_adjustment_data = ApprovalsAndRequest::whereNotIn('adjustment_date', $arrayDate)->where('user_id', $request->user_id)->where('status', 'Approved')->get();
        } else {
            if ($authUser->is_manager) {
                $userIds = User::where('manager_id', $authUserId)->pluck('id');
                $user_adjustment_data = ApprovalsAndRequest::whereNotIn('adjustment_date', $arrayDate)->whereIn('user_id', $userIds)
                    ->where('status', 'Approved')->get();
            } else {
                $user_adjustment_data = ApprovalsAndRequest::whereNotIn('adjustment_date', $arrayDate)->where('status', 'Approved')->get();
            }

        }
        // dump($user_adjustment_data);
        foreach ($user_adjustment_data as $adjustment_data) {
            // $get_request = ApprovalsAndRequest::find($adjustment_data->adjustment_id);
            $user_checkin = isset($adjustment_data) ? $adjustment_data->clock_in : '00:00:00';
            $user_checkout = isset($adjustment_data) ? $adjustment_data->clock_out : '00:00:00';
            $breakTime = isset($adjustment_data->break_adjustment) ? $adjustment_data->break_adjustment : null;
            $lunchBreak = isset($adjustment_data->lunch_adjustment) ? $adjustment_data->lunch_adjustment : null;

            $checkin_time = strtotime($user_checkin);
            $checkout_time = strtotime($user_checkout);
            $totalCurrentTimeSeconds_1 += $checkout_time - $checkin_time - ($breakTime * 60) - ($lunchBreak * 60);
            $currentCount_1++;
            // dump($totalCurrentTimeSeconds);

            if (! is_null($breakTime)) {
                $totalBreakTimeSeconds_1 += $breakTime * 60;
                $breakCount_1++;
            }
            // dump($totalBreakTimeSeconds);
            if (! is_null($lunchBreak)) {
                $totalLunchTimeSeconds_1 += $lunchBreak * 60;
                $lunchCount_1++;
            }
        }
        // dd($totalCurrentTimeSeconds,$currentCount,$totalBreakTimeSeconds,$breakCount,$totalLunchTimeSeconds,$lunchCount);
        $totalCurrentTimeSeconds = $totalCurrentTimeSeconds + $totalCurrentTimeSeconds_1;
        $totalBreakTimeSeconds = $totalBreakTimeSeconds + $totalBreakTimeSeconds_1;
        $totalLunchTimeSeconds = $totalLunchTimeSeconds + $totalLunchTimeSeconds_1;

        $currentCount = $currentCount + $currentCount_1;
        $breakCount = $breakCount + $breakCount_1;
        $lunchCount = $lunchCount + $lunchCount_1;

        // dd($totalCurrentTimeSeconds,$currentCount,$totalBreakTimeSeconds,$breakCount,$totalLunchTimeSeconds,$lunchCount);
        // Calculate average for each
        $avgCurrentTimeSeconds = $currentCount > 0 ? $totalCurrentTimeSeconds / $currentCount : 0;
        $avgBreakTimeSeconds = $breakCount > 0 ? $totalBreakTimeSeconds / $breakCount : 0;
        $avgLunchTimeSeconds = $lunchCount > 0 ? $totalLunchTimeSeconds / $lunchCount : 0;

        // Convert back to H:i:s format
        $avgCurrentTime = $this->convertSecondsToTime($avgCurrentTimeSeconds);
        $avgBreakTime = $this->convertSecondsToTime($avgBreakTimeSeconds);
        $avgLunchTime = $this->convertSecondsToTime($avgLunchTimeSeconds);
        $response['averageLunchMinutes'] = 0;
        $response['averageBreakMinutes'] = 0;
        $response['averageShiftMinutes'] = 0;
        $response['averageShiftHours'] = $avgCurrentTime;
        $response['averageLunchtHours'] = $avgLunchTime;
        $response['averageBreakHours'] = $avgBreakTime;
        $response['data'] = $data;
        // $response['data'] = $data;
        if ($data) {
            return response()->json([
                'ApiName' => 'userAuditLogs',
                'status' => true,
                'data' => $response,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'userAuditLogs',
                'status' => true,
                'data' => $response,
            ], 200);
        }

    }

    public function approvedTimeAdjustment($approvalsAndRequest, $user_id)
    {
        $userId = $approvalsAndRequest->user_id;
        $office_id = User::where('id', $userId)->select('office_id')->first();
        $adjustmentDate = $approvalsAndRequest->adjustment_date;
        if ($userId && $adjustmentDate) {

            $userAttendance = UserAttendance::where(['user_id' => $userId, 'date' => $adjustmentDate])->first();
            if ($userAttendance) {
                $detailDelete = UserAttendanceDetail::where(['user_attendance_id' => $userAttendance->id])->delete();

                $create = UserAttendanceDetail::create([
                    'user_attendance_id' => isset($userAttendance->id) ? $userAttendance->id : null,
                    'adjustment_id' => isset($approvalsAndRequest->id) ? $approvalsAndRequest->id : 0,
                    'attendance_date' => isset($approvalsAndRequest->adjustment_date) ? $approvalsAndRequest->adjustment_date.' 00:00:00' : null,
                    'type' => 'Adjustment',
                    'office_id' => ! empty($office_id) ? $office_id->office_id : 0,
                    'entry_type' => 'Adjustment',
                    'created_by' => Auth::user()->id,
                ]
                );

                // $userAttendanceDelete = $userAttendance->delete();
            }

        }
    }

    public function paginates($items, $perPage = null, $page = null)
    {
        $total = count($items);

        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);

        $start = ($paginator->currentPage() - 1) * $perPage;

        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function userAttendenceAppoval(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_attendence_id' => 'nullable',
            'status' => 'required',
            'user_id' => 'required',
            'schedule_from' => 'required',
        ]);
        $data = null;
        if (! empty($request->user_attendence_id)) {
            $check_attendance = UserAttendance::where('id', $request->user_attendence_id)->first();
            if (! empty($check_attendance)) {
                $check_attendance->status = $request->status;
                $data = $check_attendance->save();
            } else {
                $attendence_obj = new UserAttendance;
                $attendence_obj->user_id = $request->user_id;
                $attendence_obj->current_time = '00:00:00';
                $attendence_obj->lunch_time = '00:00:00';
                $attendence_obj->break_time = '00:00:00';
                $attendence_obj->is_synced = 0;
                $attendence_obj->date = Carbon::parse($request->schedule_from)->toDateString();
                $attendence_obj->status = 1;
                $data = $attendence_obj->save();
            }

        } else {
            $check_attendance = UserAttendance::where(['user_id' => $request->user_id, 'date' => Carbon::parse($request->schedule_from)->toDateString()])->first();
            if (! empty($check_attendance)) {
                $check_attendance->status = $request->status;
                $data = $check_attendance->save();
            } else {
                $attendence_obj = new UserAttendance;
                $attendence_obj->user_id = $request->user_id;
                $attendence_obj->current_time = '00:00:00';
                $attendence_obj->lunch_time = '00:00:00';
                $attendence_obj->break_time = '00:00:00';
                $attendence_obj->is_synced = 0;
                $attendence_obj->date = Carbon::parse($request->schedule_from)->toDateString();
                $attendence_obj->status = 1;
                $data = $attendence_obj->save();
            }
        }
        if ($data) {
            return response()->json([
                'ApiName' => 'userAttendenceAppoval',
                'status' => true,
                'message' => "User's attendance status successfully updated",
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'userAttendenceAppoval',
                'status' => true,
                'data' => $response,
            ], 200);
        }

    }

    public function getUserAttendenceApprovedStatusOld(Request $request)
    {
        Carbon::setWeekStartsAt(Carbon::SUNDAY); // Optional: Set custom start of the week
        Carbon::setWeekEndsAt(Carbon::SATURDAY); // Optional: Set custom end of the week
        $office_id = $request->office_id;
        $currentDate = Carbon::now();

        if (! empty($request->start_date) && ! empty($request->end_date)) {
            $startOfWeek = $request->start_date;
            $endOfWeek = $request->end_date;
        } else {
            $now = Carbon::now();
            $startOfWeek = $now->startOfWeek()->toDateString();
            $endOfWeek = $now->endOfWeek()->toDateString();
        }
        // $checkFlag = false;
        // if ($currentDate->between($startOfWeek, $endOfWeek)) {
        //     $checkFlag =  true;
        // }
        // $endDate = $currentDate->subDay()->toDateString();
        // //dd($checkFlag,$endDate);
        // if($checkFlag){
        //     $endOfWeek = $endDate;
        // }
        if ($request->has('office_id') && $request->office_id != 'all') {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startOfWeek, $endOfWeek])
                ->where('user_schedule_details.office_id', $office_id)
                ->orderBy('users.id')
                ->get();
        } else {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startOfWeek, $endOfWeek])
                    // ->where('user_schedule_details.office_id',$office_id)
                ->orderBy('users.id')
                ->get();
        }

        // Format the data as required
        $formattedData = [];
        $sumHours = 0;
        $sumMinutes = 0;
        $sumSeconds = 0;
        foreach ($userSchedulesData as $schedule) {
            $getUserData = User::find($schedule->user_id);
            $userData['id'] = ! empty($getUserData->id) ? $getUserData->id : null;
            // $userData['first_name'] = !empty($getUserData->first_name) ? $getUserData->first_name : null;
            // $userData['middle_name'] = !empty($getUserData->middle_name) ? $getUserData->middle_name : null;
            // $userData['last_name'] = !empty($getUserData->last_name) ? $getUserData->last_name : null;
            // $userData['image'] = !empty($getUserData->image) ? $getUserData->image : null;
            // $userData['is_super_admin'] = !empty($getUserData->is_super_admin) ? $getUserData->is_super_admin : null;
            // $userData['is_manager'] = !empty($getUserData->is_manager) ? $getUserData->is_manager : null;
            // $userData['position_id'] = !empty($getUserData->position_id) ? $getUserData->position_id : null;
            // $userData['sub_position_id'] = !empty($getUserData->sub_position_id) ? $getUserData->sub_position_id : null;
            $dayName = Carbon::parse($schedule->schedule_from)->format('l');
            $dayNumber = Carbon::parse($schedule->schedule_from)->format('N');
            // dd($dayName, $dayNumber, $schedule->schedule_from);
            $timeDifference = $this->calculateTimeDifference($schedule->schedule_from, $schedule->schedule_to);
            $total_hours = $this->calculateTotalHours($schedule->schedule_from, $schedule->schedule_to);
            // return $total_hours;
            // Accumulate total hours and minutes for each user
            if (! isset($formattedData[$schedule->user_id])) {
                // $formattedData[$schedule->user_id]['totalSchedulesHours'] = 0;
                // $formattedData[$schedule->user_id]['totalSchedulesMinutes'] = 0;
                // $formattedData[$schedule->user_id]['totalWorkedHours'] = 0;
                // $formattedData[$schedule->user_id]['totalWorkedMinutes'] = 0;
                // $formattedData[$schedule->user_id]['workedHours'] = "00:00:00";

            }
            // $formattedData[$schedule->user_id]['totalSchedulesHours'] += $total_hours['hours'];
            // $formattedData[$schedule->user_id]['totalSchedulesMinutes'] += $total_hours['minutes'];
            // dd($timeDifference);
            $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
            $schedule_from_time = $this->getTimeFromDateTime($schedule->schedule_from);
            $is_available = false;
            if ($schedule_from_time == '00:00:00') {
                $is_available = true;
            }
            $req_approvals_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();

            $req_approvals_leave = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 7)
                ->where('status', 'Approved')
                ->first();
            $user_attendence = UserAttendance::where('user_id', $schedule->user_id)
                ->where('date', $schedule_from_date)
                ->first();
            $user_checkin = null;
            $user_checkout = null;
            $is_present = false;
            $is_late = false;
            $calculatedWorkedHours = '00:00:00';
            $user_attendence_status = false;
            $user_attendence_id = null;
            if (! empty($user_attendence)) {
                // $is_present = true;
                $user_attendence_status = ($user_attendence->status) ? true : false;
                $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    ->whereDate('attendance_date', $schedule_from_date)
                    ->where('adjustment_id', '>', 0)
                    ->first();
                if ($user_attendance_obj) {
                    $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                    $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                } else {
                    $user_checkin_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        ->whereDate('attendance_date', $schedule_from_date)
                        ->where('type', 'clock in')
                        ->first();
                    // $user_checkin = $user_checkin_obj->attendance_date;
                    // $user_checkout_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    //     ->whereDate('attendance_date',$schedule_from_date)
                    //     ->where('type','clock out')
                    //     ->first();
                    // $user_checkout = $user_checkout_obj->attendance_date;
                    if (! empty($user_checkin_obj)) {
                        $is_present = true;
                        $user_checkin = $user_checkin_obj->attendance_date;
                        $user_checkout_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                            ->whereDate('attendance_date', $schedule_from_date)
                            ->where('type', 'clock out')
                            ->first();
                        $user_checkout = $user_checkout_obj->attendance_date;
                    } else {
                        $user_checkin = null;
                        $user_checkout = null;
                    }
                }
                $is_late = $this->compareDateTime($user_checkin, $schedule->schedule_from);
                $total_worked_hours = $this->calculateTotalHours($user_checkin, $user_checkout);
                // $current_time = $user_attendence->current_time;
                $current_time = ! empty($user_attendence->current_time) ? $user_attendence->current_time : '00:00:00';
                $getSumWorkedHours = $this->getSumWorkedHours($current_time);
                // echo '<pre>'; print_r($getSumWorkedHours);
                $sumHours += $getSumWorkedHours['hours'];
                $sumMinutes += $getSumWorkedHours['minutes'];
                $sumSeconds += $getSumWorkedHours['seconds'];
                // echo $sumHours.'----'.$sumMinutes.'-----'.$sumSeconds;
                $calculatedWorkedHours = $this->convertToTimeFormat($sumHours, $sumMinutes, $sumSeconds);
                $formattedData[$schedule->user_id]['workedHours'] = $calculatedWorkedHours ?? '00:00:00';
                if (! empty($total_worked_hours) && ! is_null($total_worked_hours)) {
                    // $formattedData[$schedule->user_id]['totalWorkedHours'] += isset($total_worked_hours) ? $total_worked_hours['hours'] : 0;
                    // $formattedData[$schedule->user_id]['totalWorkedMinutes'] += isset($total_worked_hours) ? $total_worked_hours['minutes'] : 0;
                } else {
                    // $formattedData[$schedule->user_id]['totalWorkedHours'] =  0;
                    // $formattedData[$schedule->user_id]['totalWorkedMinutes'] =  0;
                }
            }
            // return [$req_approvals_pto,$req_approvals_leave];
            $formattedData[$schedule->user_id]['user_id'] = $schedule->user_id;
            $formattedData[$schedule->user_id]['user_data'] = ! empty($userData) ? $userData : null;
            $formattedData[$schedule->user_id]['is_flexible'] = $schedule->is_flexible;
            $formattedData[$schedule->user_id]['is_repeat'] = $schedule->is_repeat;
            // $formattedData[$schedule->user_id]['user_name'] = $schedule->first_name . ' ' . ($schedule->middle_name ?? '') . ' ' . $schedule->last_name;
            $formattedData[$schedule->user_id]['user_name'] = $schedule->first_name.' '.$schedule->last_name;
            $formattedData[$schedule->user_id]['schedules'][$dayName] = [
                'user_schedule_details_id' => $schedule->id,
                'user_attendence_id' => ! empty($user_attendence) ? $user_attendence->id : null,
                'lunch_duration' => $schedule->lunch_duration,
                'schedule_from' => $schedule->schedule_from,
                'schedule_to' => $schedule->schedule_to,
                'work_days' => $dayNumber,
                'day_name' => $dayName,
                'is_available' => $is_available,
                'clock_hours' => $timeDifference,
                'is_flexible' => $schedule->is_flexible,
                'checkPTO' => ! empty($req_approvals_pto) ? $req_approvals_pto->pto_per_day : false,
                'checkLeave' => ! empty($req_approvals_leave) ? true : false,
                'clockIn' => ! empty($user_checkin) ? $user_checkin : null,
                'clockOut' => ! empty($user_checkout) ? $user_checkout : null,
                'isPresent' => $is_present,
                'isLate' => $is_late,
                'user_attendence_status' => $user_attendence_status,
            ];
        }
        // Sort the schedules by work_days within each user's schedule
        $flattenedSchedules = [];
        foreach ($formattedData as &$userData) {
            usort($userData['schedules'], function ($a, $b) {
                return $a['work_days'] <=> $b['work_days'];
            });
            // calculate scheddules hours and total hous
            // $totalHours = $userData['totalSchedulesHours'];
            // $totalMinutes = $userData['totalSchedulesMinutes'];
            // $userData['totalSchedulesHours'] = floor($totalHours + ($totalMinutes / 60));
            // $userData['totalSchedulesMinutes'] = $totalMinutes % 60;

            // $totalWHours = $userData['totalWorkedHours'];
            // $totalWMinutes = $userData['totalWorkedMinutes'];
            // $userData['totalWorkedHours'] = floor($totalWHours + ($totalWMinutes / 60));
            // $userData['totalWorkedMinutes'] = $totalWMinutes % 60;
        }
        // Extract schedules from all users
        $schedules = array_column($formattedData, 'schedules');
        // return $schedules;
        // Flatten the schedules array
        $flattenedSchedules = array_merge(...$schedules);

        // Extract the user_attendence_status values
        $userAttendenceStatuses = array_column($flattenedSchedules, 'user_attendence_status');

        // Output the attendance status array
        // print_r($userAttendenceStatuses);
        // $userAttendenceStatuses = [1,1, 0,1,0];
        // print_r($userAttendenceStatuses);
        $checkUserAttendenceStatuses = in_array(0, $userAttendenceStatuses);
        // dd($checkUserAttendenceStatuses);
        // if($checkUserAttendenceStatuses){
        //     $attendenceStatus = true;
        //     echo "\n"; echo "======  ".$checkUserAttendenceStatuses;
        // }
        // else{
        //     $attendenceStatus = false;
        //     echo "\n"; echo "++  ".$checkUserAttendenceStatuses;
        // }
        // $userData['checkUserAttendenceStatuses'] =$attendenceStatus;
        // dd($attendence_array);
        // foreach ($formattedData as &$userData) {
        //     $totalHours = $userData['totalSchedulesHours'];
        //     $totalMinutes = $userData['totalSchedulesMinutes'];
        //     $userData['totalSchedulesHours'] = floor($totalHours + ($totalMinutes / 60));
        //     $userData['totalSchedulesMinutes'] = $totalMinutes % 60;

        //     $totalWHours = $userData['totalWorkedHours'];
        //     $totalWMinutes = $userData['totalWorkedMinutes'];
        //     $userData['totalWorkedHours'] = floor($totalWHours + ($totalWMinutes / 60));
        //     $userData['totalWorkedMinutes'] = $totalWMinutes % 60;
        // }
        // Prepare the response
        if (! empty($formattedData)) {
            return response()->json([
                'ApiName' => 'getUserAttendenceApprovedStatus',
                'status' => true,
                'attendenceApprovedStatus' => ($checkUserAttendenceStatuses) ? 'Not Approved' : 'Approved',
                // 'data' => array_values($formattedData), // Resetting array keys to start from 0
                'startOfWeek' => $startOfWeek,
                'endOfWeek' => $endOfWeek,
                'data' => [], // Resetting array keys to start from 0
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'getUserAttendenceApprovedStatus',
                'status' => true,
                'attendenceStatus' => ($checkUserAttendenceStatuses) ? 'Not Approved' : 'Approved',
                'message' => 'No schedules found for the specified week.',
                'startOfWeek' => $startOfWeek,
                'endOfWeek' => $endOfWeek,
                'data' => [],
            ], 200);
        }
        // return $userSchedulesData;
    }

    public function getUserAttendenceApprovedStatus(Request $request)
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        Carbon::setWeekStartsAt(Carbon::SUNDAY); // Optional: Set custom start of the week
        Carbon::setWeekEndsAt(Carbon::SATURDAY); // Optional: Set custom end of the week
        $office_id = $request->office_id;
        $currentDate = Carbon::now();

        if (! empty($request->start_date) && ! empty($request->end_date)) {
            $startOfWeek = $request->start_date;
            $endOfWeek = $request->end_date;
        } else {
            $now = Carbon::now();
            $startOfWeek = $now->startOfWeek()->toDateString();
            $endOfWeek = $now->endOfWeek()->toDateString();
        }
        // $checkFlag = false;
        // if ($currentDate->between($startOfWeek, $endOfWeek)) {
        //     $checkFlag =  true;
        // }
        // $endDate = $currentDate->subDay()->toDateString();
        // //dd($checkFlag,$endDate);
        // if($checkFlag){
        //     $endOfWeek = $endDate;
        // }
        if ($request->has('office_id') && $request->office_id != 'all') {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startOfWeek, $endOfWeek])
                ->where('user_schedule_details.office_id', $office_id)
                ->orderBy('users.id');
        } else {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startOfWeek, $endOfWeek])
                // ->where('user_schedule_details.office_id',$office_id)
                ->orderBy('users.id');
        }
        $userSchedulesData = $userSchedulesData->paginate($perpage);
        // return $userSchedulesData;

        // Format the data as required
        $formattedData = [];
        $sumHours = 0;
        $sumMinutes = 0;
        $sumSeconds = 0;
        foreach ($userSchedulesData as $schedule) {
            $getUserData = User::find($schedule->user_id);
            $userData['id'] = ! empty($getUserData->id) ? $getUserData->id : null;
            // $userData['first_name'] = !empty($getUserData->first_name) ? $getUserData->first_name : null;
            // $userData['middle_name'] = !empty($getUserData->middle_name) ? $getUserData->middle_name : null;
            // $userData['last_name'] = !empty($getUserData->last_name) ? $getUserData->last_name : null;
            // $userData['image'] = !empty($getUserData->image) ? $getUserData->image : null;
            // $userData['is_super_admin'] = !empty($getUserData->is_super_admin) ? $getUserData->is_super_admin : null;
            // $userData['is_manager'] = !empty($getUserData->is_manager) ? $getUserData->is_manager : null;
            // $userData['position_id'] = !empty($getUserData->position_id) ? $getUserData->position_id : null;
            // $userData['sub_position_id'] = !empty($getUserData->sub_position_id) ? $getUserData->sub_position_id : null;
            $dayName = Carbon::parse($schedule->schedule_from)->format('l');
            $dayNumber = Carbon::parse($schedule->schedule_from)->format('N');
            // dd($dayName, $dayNumber, $schedule->schedule_from);
            $timeDifference = $this->calculateTimeDifference($schedule->schedule_from, $schedule->schedule_to);
            $total_hours = $this->calculateTotalHours($schedule->schedule_from, $schedule->schedule_to);
            // return $total_hours;
            // Accumulate total hours and minutes for each user
            if (! isset($formattedData[$schedule->user_id])) {
                // $formattedData[$schedule->user_id]['totalSchedulesHours'] = 0;
                // $formattedData[$schedule->user_id]['totalSchedulesMinutes'] = 0;
                // $formattedData[$schedule->user_id]['totalWorkedHours'] = 0;
                // $formattedData[$schedule->user_id]['totalWorkedMinutes'] = 0;
                // $formattedData[$schedule->user_id]['workedHours'] = "00:00:00";

            }
            // $formattedData[$schedule->user_id]['totalSchedulesHours'] += $total_hours['hours'];
            // $formattedData[$schedule->user_id]['totalSchedulesMinutes'] += $total_hours['minutes'];
            // dd($timeDifference);
            $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
            $schedule_from_time = $this->getTimeFromDateTime($schedule->schedule_from);
            $is_available = false;
            if ($schedule_from_time == '00:00:00') {
                $is_available = true;
            }
            $req_approvals_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();

            $req_approvals_leave = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 7)
                ->where('status', 'Approved')
                ->first();
            $user_attendence = UserAttendance::where('user_id', $schedule->user_id)
                ->where('date', $schedule_from_date)
                ->first();
            $user_checkin = null;
            $user_checkout = null;
            $is_present = false;
            $is_late = false;
            $calculatedWorkedHours = '00:00:00';
            $user_attendence_status = false;
            $user_attendence_id = null;
            if (! empty($user_attendence)) {
                // $is_present = true;
                $user_attendence_status = ($user_attendence->status) ? true : false;
                $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    ->whereDate('attendance_date', $schedule_from_date)
                    ->where('adjustment_id', '>', 0)
                    ->first();
                if ($user_attendance_obj) {
                    $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                    $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                } else {
                    $user_checkin_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        ->whereDate('attendance_date', $schedule_from_date)
                        ->where('type', 'clock in')
                        ->first();
                    // $user_checkin = $user_checkin_obj->attendance_date;
                    // $user_checkout_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    //     ->whereDate('attendance_date',$schedule_from_date)
                    //     ->where('type','clock out')
                    //     ->first();
                    // $user_checkout = $user_checkout_obj->attendance_date;
                    if (! empty($user_checkin_obj)) {
                        $is_present = true;
                        $user_checkin = $user_checkin_obj->attendance_date;
                        $user_checkout_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                            ->whereDate('attendance_date', $schedule_from_date)
                            ->where('type', 'clock out')
                            ->first();
                        $user_checkout = $user_checkout_obj->attendance_date;
                    } else {
                        $user_checkin = null;
                        $user_checkout = null;
                    }
                }
                $is_late = $this->compareDateTime($user_checkin, $schedule->schedule_from);
                $total_worked_hours = $this->calculateTotalHours($user_checkin, $user_checkout);
                // $current_time = $user_attendence->current_time;
                $current_time = ! empty($user_attendence->current_time) ? $user_attendence->current_time : '00:00:00';
                $getSumWorkedHours = $this->getSumWorkedHours($current_time);
                // echo '<pre>'; print_r($getSumWorkedHours);
                $sumHours += $getSumWorkedHours['hours'];
                $sumMinutes += $getSumWorkedHours['minutes'];
                $sumSeconds += $getSumWorkedHours['seconds'];
                // echo $sumHours.'----'.$sumMinutes.'-----'.$sumSeconds;
                $calculatedWorkedHours = $this->convertToTimeFormat($sumHours, $sumMinutes, $sumSeconds);
                $formattedData[$schedule->user_id]['workedHours'] = $calculatedWorkedHours ?? '00:00:00';
                if (! empty($total_worked_hours) && ! is_null($total_worked_hours)) {
                    // $formattedData[$schedule->user_id]['totalWorkedHours'] += isset($total_worked_hours) ? $total_worked_hours['hours'] : 0;
                    // $formattedData[$schedule->user_id]['totalWorkedMinutes'] += isset($total_worked_hours) ? $total_worked_hours['minutes'] : 0;
                } else {
                    // $formattedData[$schedule->user_id]['totalWorkedHours'] =  0;
                    // $formattedData[$schedule->user_id]['totalWorkedMinutes'] =  0;
                }
            }
            // return [$req_approvals_pto,$req_approvals_leave];
            $formattedData[$schedule->user_id]['user_id'] = $schedule->user_id;
            $formattedData[$schedule->user_id]['user_data'] = ! empty($userData) ? $userData : null;
            $formattedData[$schedule->user_id]['is_flexible'] = $schedule->is_flexible;
            $formattedData[$schedule->user_id]['is_repeat'] = $schedule->is_repeat;
            // $formattedData[$schedule->user_id]['user_name'] = $schedule->first_name . ' ' . ($schedule->middle_name ?? '') . ' ' . $schedule->last_name;
            $formattedData[$schedule->user_id]['user_name'] = $schedule->first_name.' '.$schedule->last_name;
            $formattedData[$schedule->user_id]['schedules'][$dayName] = [
                'user_schedule_details_id' => $schedule->id,
                'user_attendence_id' => ! empty($user_attendence) ? $user_attendence->id : null,
                'lunch_duration' => $schedule->lunch_duration,
                'schedule_from' => $schedule->schedule_from,
                'schedule_to' => $schedule->schedule_to,
                'work_days' => $dayNumber,
                'day_name' => $dayName,
                'is_available' => $is_available,
                'clock_hours' => $timeDifference,
                'is_flexible' => $schedule->is_flexible,
                'checkPTO' => ! empty($req_approvals_pto) ? $req_approvals_pto->pto_per_day : false,
                'checkLeave' => ! empty($req_approvals_leave) ? true : false,
                'clockIn' => ! empty($user_checkin) ? $user_checkin : null,
                'clockOut' => ! empty($user_checkout) ? $user_checkout : null,
                'isPresent' => $is_present,
                'isLate' => $is_late,
                'user_attendence_status' => $user_attendence_status,
                'user_attendence_approved_status' => $user_attendence_status,

            ];
        }
        // Sort the schedules by work_days within each user's schedule
        $flattenedSchedules = [];
        foreach ($formattedData as &$userData) {
            usort($userData['schedules'], function ($a, $b) {
                return $a['work_days'] <=> $b['work_days'];
            });
            // calculate scheddules hours and total hous
            // $totalHours = $userData['totalSchedulesHours'];
            // $totalMinutes = $userData['totalSchedulesMinutes'];
            // $userData['totalSchedulesHours'] = floor($totalHours + ($totalMinutes / 60));
            // $userData['totalSchedulesMinutes'] = $totalMinutes % 60;

            // $totalWHours = $userData['totalWorkedHours'];
            // $totalWMinutes = $userData['totalWorkedMinutes'];
            // $userData['totalWorkedHours'] = floor($totalWHours + ($totalWMinutes / 60));
            // $userData['totalWorkedMinutes'] = $totalWMinutes % 60;
        }
        // Extract schedules from all users
        $schedules = array_column($formattedData, 'schedules');
        // return $schedules;
        // Flatten the schedules array
        $flattenedSchedules = array_merge(...$schedules);

        // Extract the user_attendence_status values
        // $userAttendenceStatuses = array_column($flattenedSchedules, 'user_attendence_status');
        $userAttendenceStatuses = array_column($flattenedSchedules, 'user_attendence_approved_status');

        // Output the attendance status array
        // print_r($userAttendenceStatuses);
        // $userAttendenceStatuses = [1,1, 0,1,0];
        // print_r($userAttendenceStatuses);
        $checkUserAttendenceStatuses = in_array(0, $userAttendenceStatuses);
        // dd($checkUserAttendenceStatuses);
        // if($checkUserAttendenceStatuses){
        //     $attendenceStatus = true;
        //     echo "\n"; echo "======  ".$checkUserAttendenceStatuses;
        // }
        // else{
        //     $attendenceStatus = false;
        //     echo "\n"; echo "++  ".$checkUserAttendenceStatuses;
        // }
        // $userData['checkUserAttendenceStatuses'] =$attendenceStatus;
        // dd($attendence_array);
        // foreach ($formattedData as &$userData) {
        //     $totalHours = $userData['totalSchedulesHours'];
        //     $totalMinutes = $userData['totalSchedulesMinutes'];
        //     $userData['totalSchedulesHours'] = floor($totalHours + ($totalMinutes / 60));
        //     $userData['totalSchedulesMinutes'] = $totalMinutes % 60;

        //     $totalWHours = $userData['totalWorkedHours'];
        //     $totalWMinutes = $userData['totalWorkedMinutes'];
        //     $userData['totalWorkedHours'] = floor($totalWHours + ($totalWMinutes / 60));
        //     $userData['totalWorkedMinutes'] = $totalWMinutes % 60;
        // }
        // Prepare the response
        if (! empty($formattedData)) {
            return response()->json([
                'ApiName' => 'getUserAttendenceApprovedStatus',
                'status' => true,
                'attendenceApprovedStatus' => ($checkUserAttendenceStatuses) ? 'Not Approved' : 'Approved',
                // 'data' => array_values($formattedData), // Resetting array keys to start from 0
                'startOfWeek' => $startOfWeek,
                'endOfWeek' => $endOfWeek,
                'data' => [], // Resetting array keys to start from 0
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'getUserAttendenceApprovedStatus',
                'status' => true,
                'attendenceStatus' => ($checkUserAttendenceStatuses) ? 'Not Approved' : 'Approved',
                'message' => 'No schedules found for the specified week.',
                'startOfWeek' => $startOfWeek,
                'endOfWeek' => $endOfWeek,
                'data' => [],
            ], 200);
        }
        // return $userSchedulesData;
    }

    public function approvedAttendanceStatusOld(ApprovedAttendanceStatusRequest $request)
    {
        if (! empty($request->start_date) && ! empty($request->end_date)) {
            $startOfWeek = $request->start_date;
            $endOfWeek = $request->end_date;
        } else {
            $now = Carbon::now();
            $startOfWeek = $now->startOfWeek()->toDateString();
            $endOfWeek = $now->endOfWeek()->toDateString();
        }
        $usersId = $request->user_id;
        $checkFlag = false;
        $currentDate = Carbon::now();
        if ($currentDate->between($startOfWeek, $endOfWeek)) {
            $checkFlag = true;
        }
        $endDate = $currentDate->subDay()->toDateString();
        // dd($checkFlag,$endDate);
        if ($checkFlag) {
            $endOfWeek = $endDate;
        }
        if ($request->has('office_id') && $request->office_id != 'all') {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startOfWeek, $endOfWeek])
                ->where('user_schedule_details.office_id', $office_id)
                ->whereIn('user_schedules.user_id', $usersId)
                ->orderBy('users.id')
                ->get();
        } else {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id'
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startOfWeek, $endOfWeek])
                ->whereIn('user_schedules.user_id', $usersId)
                ->orderBy('users.id')
                ->get();
        }
        // return $userSchedulesData;
        foreach ($userSchedulesData as $schedule) {
            $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
            $req_approvals_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();

            $req_approvals_leave = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 7)
                ->where('status', 'Approved')
                ->first();
            $user_attendence = UserAttendance::where('user_id', $schedule->user_id)
                ->where('date', $schedule_from_date)
                ->first();
            if (! empty($user_attendence)) {
                // $is_present = true;
                $user_attendence_status = (isset($user_attendence->status) && $user_attendence->status) ? true : false;
                if ($user_attendence_status == false) {
                    $user_attendence->status = 1;
                    $user_attendence->save();
                }
                $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    ->whereDate('attendance_date', $schedule_from_date)
                    ->where('adjustment_id', '>', 0)
                    ->first();
                if ($user_attendance_obj) {
                    $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                    $user_attendence_status = (! empty($get_request) && $get_request->status == 'Approved') ? true : false;
                    // $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    // $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                }

            } else {
                $check_attendance = UserAttendance::where(['user_id' => $schedule->user_id, 'date' => $schedule_from_date])->first();
                if (! empty($check_attendance)) {
                    $check_attendance->status = 1;
                    $data = $check_attendance->save();
                } else {
                    $attendence_obj = new UserAttendance;
                    $attendence_obj->user_id = $schedule->user_id;
                    $attendence_obj->current_time = '00:00:00';
                    $attendence_obj->lunch_time = '00:00:00';
                    $attendence_obj->break_time = '00:00:00';
                    $attendence_obj->is_synced = 0;
                    $attendence_obj->date = $schedule_from_date;
                    $attendence_obj->status = 1;
                    $data = $attendence_obj->save();
                }
            }
        }

        return response()->json([
            'ApiName' => 'userAttendenceAppoval',
            'status' => true,
            'message' => "User's attendance status successfully updated",
        ], 200);
    }

    public function approvedAttendanceStatusOld2(Request $request)
    {
        if (! empty($request->start_date) && ! empty($request->end_date)) {
            $startOfWeek = $request->start_date;
            $endOfWeek = $request->end_date;
        } else {
            $now = Carbon::now();
            $startOfWeek = $now->startOfWeek()->toDateString();
            $endOfWeek = $now->endOfWeek()->toDateString();
        }
        if ($request->has('office_id') && ! empty($request->office_id) && $request->office_id != 'all') {
            $office_id = $request->office_id;
        }
        $usersId = $request->user_id;
        // $checkFlag = false;
        // $currentDate = Carbon::now();
        // if ($currentDate->between($startOfWeek, $endOfWeek)) {
        //     $checkFlag =  true;
        // }
        $checkFlag = false;
        $currentDate = Carbon::now()->toDateString(); // 'Y-m-d' format
        // $currentDate = Carbon::now()->startOfDay()->toDateString(); // 'Y-m-d' format
        // $currentDate = '2024-08-24';
        // $endDate = Carbon::now()->subDay(1)->toDateString();
        $endDate = Carbon::parse($currentDate)->subDay(1)->toDateString();
        if ($endDate >= $startOfWeek && $endDate <= $endOfWeek) {
            $checkFlag = true;
        }

        if ($checkFlag) {
            $endOfWeek = $endDate;
        } else {
            $endOfWeek = $currentDate;
        }
        // dd($startOfWeek, $endOfWeek, $endDate, $checkFlag);
        if ($startOfWeek == $endOfWeek && ! $checkFlag) {
            return response()->json([
                'ApiName' => 'approvedAttendanceStatus',
                'status' => false,
                'message' => 'Attendance will not approved, due to startOfWeek and endOfWeek should not be same',
            ], 400);
        }
        // dd($startOfWeek, $endOfWeek, $endDate, $checkFlag);
        if ($request->has('office_id') && $request->office_id != 'all') {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween(DB::raw('DATE(user_schedule_details.schedule_from)'), [$startOfWeek, $endOfWeek])
                ->where('user_schedule_details.office_id', $office_id)
                    // ->whereIn('user_schedules.user_id',$usersId)
                ->orderBy('users.id')
                ->get();
        } else {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                    // ->whereBetween('user_schedule_details.schedule_from', [$startOfWeek, $endOfWeek])
                ->whereBetween(DB::raw('DATE(user_schedule_details.schedule_from)'), [$startOfWeek, $endOfWeek])
                    // ->whereIn('user_schedules.user_id',$usersId)
                ->orderBy('users.id')
                ->get();
        }
        // return $userSchedulesData;
        $res_arr = [];
        foreach ($userSchedulesData as $schedule) {
            $res = $this->update_attendance_status_schedules_details($schedule);
            // array_push($res_arr,$res);
            // return $schedule;
            $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
            $req_approvals_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();

            $req_approvals_leave = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 7)
                ->where('status', 'Approved')
                ->first();
            $user_attendence = UserAttendance::where('user_id', $schedule->user_id)
                ->where('date', $schedule_from_date)
                ->first();
            if (! empty($user_attendence)) {
                // $is_present = true;

                $res = $this->update_attendance_id_schedules_details($schedule->id, $user_attendence);
                /*    start send  data to payroll for finalize */
                $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    ->whereDate('attendance_date', $schedule_from_date)
                    ->where('adjustment_id', '>', 0)
                    ->first();
                if ($user_attendance_obj) {
                    $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                    $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                    $clockIn = Carbon::parse($user_checkin);
                    $clockOut = Carbon::parse($user_checkout);

                    // Calculate the difference in seconds
                    $diffInSeconds = $clockIn->diffInSeconds($clockOut);

                    // Convert the difference to a 00:00:00 format
                    $timeDifference = gmdate('H:i:s', $diffInSeconds);
                    $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : 0;
                    $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : 0;
                    if (! is_null($lunchBreak)) {
                        $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                    }
                    if (! is_null($breakTime)) {
                        $breakTime = gmdate('H:i:s', $breakTime * 60);
                    }
                    $lunchDuration = Carbon::parse($lunchBreak)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                    $breakDuration = Carbon::parse($breakTime)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                    $totalWorkedSeconds = $diffInSeconds - ($lunchDuration + $breakDuration);
                    $finalTimeDifference = gmdate('H:i:s', $totalWorkedSeconds);
                    // need too minus lunch and break
                    $attendanceData = [];
                    $attendanceData['id'] = $get_request->id;
                    $attendanceData['user_id'] = $get_request->user_id;
                    $attendanceData['current_time'] = $finalTimeDifference;
                    $attendanceData['lunch_time'] = $lunchBreak;
                    $attendanceData['break_time'] = $breakTime;
                    $attendanceData['date'] = $get_request->adjustment_date;
                    if (! empty($attendanceData['current_time'])) {
                        $s = $this->payroll_wages_create($attendanceData);
                        // dd($s);
                    }

                } else {
                    $attendanceData = [];
                    $attendanceData['id'] = $user_attendence->id;
                    $attendanceData['user_id'] = $user_attendence->user_id;
                    $attendanceData['current_time'] = $user_attendence->current_time;
                    $attendanceData['lunch_time'] = $user_attendence->lunch_time;
                    $attendanceData['break_time'] = $user_attendence->break_time;
                    $attendanceData['date'] = $user_attendence->date;
                    if (! empty($attendanceData['current_time'])) {
                        $s = $this->payroll_wages_create($attendanceData);
                        // dd($s);
                    }
                    // dd($s);
                }

                // $s = $this->payroll_wages_create($attendanceData);
                // dd($s);
                /*    end  data to payroll for finalize */
                $user_attendence_status = (isset($user_attendence->status) && $user_attendence->status) ? true : false;
                if ($user_attendence_status == false) {
                    $user_attendence->status = 1;
                    $user_attendence->save();
                }
                $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    ->whereDate('attendance_date', $schedule_from_date)
                    ->where('adjustment_id', '>', 0)
                    ->first();
                if ($user_attendance_obj) {
                    $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                    $user_attendence_status = (! empty($get_request) && $get_request->status == 'Approved') ? true : false;
                    // $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    // $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                }
                // check clockIn / clockOut data for everee
                /* start send data to everee */
                // $payload = [];
                // $attendance_details_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                //     ->get()->toArray();

                // $types = array_column($attendance_details_obj, 'type');
                // $dates = array_column($attendance_details_obj, 'attendance_date');

                // $findUser = User::find($schedule->user_id);
                // $payload['clockIn'] = $dates[array_search('clock in', $types)];
                // $payload['clockOut'] = $dates[array_search('clock out', $types)];
                // $payload['lunch'] = $dates[array_search('lunch', $types)];
                // $payload['lunchEnd'] = $dates[array_search('end lunch', $types)];
                // $payload['break'] = $dates[array_search('break', $types)];
                // $payload['breakEnd'] = $dates[array_search('end break', $types)];
                // $payload['workerId'] = !empty($findUser->everee_workerId) ? $findUser->everee_workerId :  null;
                // $payload['externalWorkerId'] = !empty($findUser->employee_id) ? $findUser->employee_id : null;
                // // dd($payload);
                // if(!empty($findUser->everee_workerId)){
                //     $getResponse = $this->send_timesheet_data($payload);
                //     if(empty($user_attendence->everee_status) || is_null($user_attendence->everee_status)){
                //         $user_attendence->everee_status = $getResponse;
                //         $user_attendence->save();
                //     }
                // }
                /* end send data to everee */

            } else {
                $get_request = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                    ->where('adjustment_date', '=', $schedule_from_date)
                    ->where('adjustment_type_id', 9)
                    ->where('status', 'Approved')
                    ->first();
                if (! empty($get_request)) {
                    $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                    $clockIn = Carbon::parse($user_checkin);
                    $clockOut = Carbon::parse($user_checkout);

                    // Calculate the difference in seconds
                    $diffInSeconds = $clockIn->diffInSeconds($clockOut);

                    // Convert the difference to a 00:00:00 format
                    $timeDifference = gmdate('H:i:s', $diffInSeconds);
                    $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : 0;
                    $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : 0;
                    if (! is_null($lunchBreak)) {
                        $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                    }
                    if (! is_null($breakTime)) {
                        $breakTime = gmdate('H:i:s', $breakTime * 60);
                    }
                    // need to minus lunch and break time
                    $lunchDuration = Carbon::parse($lunchBreak)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                    $breakDuration = Carbon::parse($breakTime)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                    $totalWorkedSeconds = $diffInSeconds - ($lunchDuration + $breakDuration);
                    $finalTimeDifference = gmdate('H:i:s', $totalWorkedSeconds);
                    $attendanceData = [];
                    $attendanceData['id'] = $get_request->id;
                    $attendanceData['user_id'] = $get_request->user_id;
                    $attendanceData['current_time'] = $finalTimeDifference;
                    $attendanceData['lunch_time'] = $lunchBreak;
                    $attendanceData['break_time'] = $breakTime;
                    $attendanceData['date'] = $get_request->adjustment_date;
                    if (! empty($attendanceData['current_time'])) {
                        $s = $this->payroll_wages_create($attendanceData);
                        // dd($s);
                    }
                    // dd($s);
                }
                $check_attendance = UserAttendance::where(['user_id' => $schedule->user_id, 'date' => $schedule_from_date])->first();
                if (! empty($check_attendance)) {
                    // $check_attendance->status = 1;
                    // $data = $check_attendance->save();
                } else {
                    // $attendence_obj = new UserAttendance();
                    // $attendence_obj->user_id = $schedule->user_id;
                    // $attendence_obj->current_time = "00:00:00";
                    // $attendence_obj->lunch_time = "00:00:00";
                    // $attendence_obj->break_time = "00:00:00";
                    // $attendence_obj->is_synced = 0;
                    // $attendence_obj->date = $schedule_from_date;
                    // $attendence_obj->status = 1;
                    // $data = $attendence_obj->save();
                }
            }
        }

        return response()->json([
            'ApiName' => 'userAttendenceAppoval',
            'status' => true,
            'message' => "User's attendance status successfully updated",
        ], 200);
    }

    public function approvedAttendanceStatus(Request $request)
    {
        // $timezone = 'America/Denver';
        // $timezone = 'Asia/Kolkata';
        $profileData = CompanyProfile::where('id', 1)->first();
        $timezone_arr = explode(' ', $profileData->time_zone);
        $input = $timezone_arr[0];
        $timezone_val = preg_match('/\d{2}:\d{2}/', $input, $matches);
        $timePart = $matches[0];
        // return $timePart;
        $timePartVal = explode(':', $timePart);
        $timePartHour = $timePartVal[0];
        $timePartMinute = $timePartVal[1];
        $timezone = timezone_name_from_abbr('', -($timePartHour) * 3600, 0);
        $closedUsers = '';
        $addedUserIds = [];
        // return $timezone;
        if (! empty($request->start_date) && ! empty($request->end_date)) {
            // $startOfWeek = $request->start_date;
            // $endOfWeek = $request->end_date;
            $startOfWeek = Carbon::parse($request->start_date, $timezone)->toDateString();
            $endOfWeek = Carbon::parse($request->end_date, $timezone)->toDateString();
        } else {
            // $now = Carbon::now();
            // $startOfWeek = $now->startOfWeek()->toDateString();
            // $endOfWeek = $now->endOfWeek()->toDateString();
            $now = Carbon::now($timezone);
            $startOfWeek = $now->startOfWeek()->toDateString();
            $endOfWeek = $now->endOfWeek()->toDateString();
        }
        if ($request->has('office_id') && ! empty($request->office_id) && $request->office_id != 'all') {
            $office_id = $request->office_id;
        }
        $usersId = $request->user_id;
        // $checkFlag = false;
        // $currentDate = Carbon::now();
        // if ($currentDate->between($startOfWeek, $endOfWeek)) {
        //     $checkFlag =  true;
        // }
        $checkFlag = false;

        // $currentDate = '2024-08-24';
        // $endDate = Carbon::now()->subDay(1)->toDateString();

        // $currentDate = Carbon::now()->toDateString(); // 'Y-m-d' format
        // $endDate = Carbon::parse($currentDate)->subDay(1)->toDateString();
        $currentDate = Carbon::now($timezone)->startOfDay()->toDateString();
        $endDate = Carbon::parse($currentDate, $timezone)->subDay(1)->toDateString();
        if ($endDate >= $startOfWeek && $endDate <= $endOfWeek) {
            $checkFlag = true;
        }

        if ($checkFlag) {
            $endOfWeek = $endDate;
        } else {
            $endOfWeek = $currentDate;
        }
        // dd('ok',$currentDate, $startOfWeek, $endOfWeek, $endDate, $checkFlag);
        if ($startOfWeek == $endOfWeek && ! $checkFlag) {
            return response()->json([
                'ApiName' => 'approvedAttendanceStatus',
                'status' => false,
                'message' => 'Attendance will not approved, due to startOfWeek and endOfWeek should not e same',
            ], 400);
        }
        // dd($startOfWeek, $endOfWeek, $endDate, $checkFlag);
        if ($request->has('office_id') && $request->office_id != 'all') {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween(DB::raw('DATE(user_schedule_details.schedule_from)'), [$startOfWeek, $endOfWeek])
                ->where('user_schedule_details.office_id', $office_id)
                    // ->whereIn('user_schedules.user_id',$usersId)
                ->orderBy('users.id')
                ->get();
        } else {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                    // ->whereBetween('user_schedule_details.schedule_from', [$startOfWeek, $endOfWeek])
                ->whereBetween(DB::raw('DATE(user_schedule_details.schedule_from)'), [$startOfWeek, $endOfWeek])
                    // ->whereIn('user_schedules.user_id',$usersId)
                ->orderBy('users.id')
                ->get();
        }
        // return $userSchedulesData;
        $res_arr = [];
        foreach ($userSchedulesData as $schedule) {
            $res = $this->update_attendance_status_schedules_details($schedule);
            // array_push($res_arr,$res);
            // return $schedule;
            $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
            $req_approvals_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();

            $req_approvals_leave = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 7)
                ->where('status', 'Approved')
                ->first();
            $user_attendence = UserAttendance::where('user_id', $schedule->user_id)
                ->where('date', $schedule_from_date)
                ->first();
            if (! empty($user_attendence)) {
                // $is_present = true;

                $res = $this->update_attendance_id_schedules_details($schedule->id, $user_attendence);
                /*    start send  data to payroll for finalize */
                $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    ->whereDate('attendance_date', $schedule_from_date)
                    ->where('adjustment_id', '>', 0)
                    ->first();
                if ($user_attendance_obj) {
                    $check_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                        ->where('start_date', '<=', $schedule_from_date)
                        ->where('end_date', '>=', $schedule_from_date)
                        ->where('adjustment_type_id', 8)
                        ->where('status', 'Approved')
                        ->first();
                    $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                    $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                    $clockIn = Carbon::parse($user_checkin);
                    if (! empty($check_pto) && isset($check_pto->pto_hours_perday) && ! is_null($check_pto->pto_hours_perday)) {
                        $newClockOut = Carbon::parse($user_checkout);
                        $clockOut = $newClockOut->addHours($check_pto->pto_hours_perday);
                    } else {
                        $clockOut = Carbon::parse($user_checkout);
                    }

                    // Calculate the difference in seconds
                    $diffInSeconds = $clockIn->diffInSeconds($clockOut);

                    // Convert the difference to a 00:00:00 format
                    $timeDifference = gmdate('H:i:s', $diffInSeconds);
                    $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : 0;
                    $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : 0;
                    if (! is_null($lunchBreak)) {
                        $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                    }
                    if (! is_null($breakTime)) {
                        $breakTime = gmdate('H:i:s', $breakTime * 60);
                    }
                    $lunchDuration = Carbon::parse($lunchBreak)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                    $breakDuration = Carbon::parse($breakTime)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                    $totalWorkedSeconds = $diffInSeconds - ($lunchDuration + $breakDuration);
                    $finalTimeDifference = gmdate('H:i:s', $totalWorkedSeconds);
                    // need too minus lunch and break
                    $attendanceData = [];
                    $attendanceData['id'] = $get_request->id;
                    $attendanceData['user_id'] = $get_request->user_id;
                    $attendanceData['current_time'] = $finalTimeDifference;
                    $attendanceData['lunch_time'] = $lunchBreak;
                    $attendanceData['break_time'] = $breakTime;
                    $attendanceData['date'] = $get_request->adjustment_date;
                    if (! empty($attendanceData['current_time'])) {
                        $s = $this->payroll_wages_create($attendanceData);
                        // dd($s);
                    }

                } else {
                    $attendanceData = [];
                    $check_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                        ->where('start_date', '<=', $schedule_from_date)
                        ->where('end_date', '>=', $schedule_from_date)
                        ->where('adjustment_type_id', 8)
                        ->where('status', 'Approved')
                        ->first();
                    if (! empty($check_pto) && isset($check_pto->pto_hours_perday) && ! is_null($check_pto->pto_hours_perday)) {
                        if (! empty($user_attendence->current_time)) {
                            $current_time = Carbon::createFromTimeString($user_attendence->current_time);
                        } else {
                            // $current_time = Carbon::createFromTimeString("00:00:00");
                            $current_time = null;
                        }
                        if (! empty($current_time) && ! is_null($current_time)) {
                            $newTime = $current_time->addHours($check_pto->pto_hours_perday);
                            $attendanceData['current_time'] = $newTime->toTimeString();
                        } else {
                            $attendanceData['current_time'] = null;
                        }
                    } else {
                        $attendanceData['current_time'] = $user_attendence->current_time;
                    }

                    $attendanceData['id'] = $user_attendence->id;
                    $attendanceData['user_id'] = $user_attendence->user_id;
                    // $attendanceData['current_time'] = $user_attendence->current_time;
                    $attendanceData['lunch_time'] = $user_attendence->lunch_time;
                    $attendanceData['break_time'] = $user_attendence->break_time;
                    $attendanceData['date'] = $user_attendence->date;
                    if (! empty($attendanceData['current_time'])) {
                        $s = $this->payroll_wages_create($attendanceData);
                        // dd($s);
                    }
                    // dd($s);
                }

                // $s = $this->payroll_wages_create($attendanceData);
                // dd($s);
                /*    end  data to payroll for finalize */
                $user_attendence_status = (isset($user_attendence->status) && $user_attendence->status) ? true : false;
                if ($user_attendence_status == false) {
                    $user_attendence->status = 1;
                    $user_attendence->save();
                }
                $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    ->whereDate('attendance_date', $schedule_from_date)
                    ->where('adjustment_id', '>', 0)
                    ->first();
                if ($user_attendance_obj) {
                    $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                    $user_attendence_status = (! empty($get_request) && $get_request->status == 'Approved') ? true : false;
                    // $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    // $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                }

            } else {
                $get_request = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                    ->where('adjustment_date', '=', $schedule_from_date)
                    ->where('adjustment_type_id', 9)
                    ->where('status', 'Approved')
                    ->first();
                if (! empty($get_request)) {
                    $check_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                        ->where('start_date', '<=', $schedule_from_date)
                        ->where('end_date', '>=', $schedule_from_date)
                        ->where('adjustment_type_id', 8)
                        ->where('status', 'Approved')
                        ->first();
                    $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                    $clockIn = Carbon::parse($user_checkin);
                    if (! empty($check_pto) && isset($check_pto->pto_hours_perday) && ! is_null($check_pto->pto_hours_perday)) {
                        $newClockOut = Carbon::parse($user_checkout);
                        $clockOut = $newClockOut->addHours($check_pto->pto_hours_perday);
                    } else {
                        $clockOut = Carbon::parse($user_checkout);
                    }

                    // Calculate the difference in seconds
                    $diffInSeconds = $clockIn->diffInSeconds($clockOut);

                    // Convert the difference to a 00:00:00 format
                    $timeDifference = gmdate('H:i:s', $diffInSeconds);
                    $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : 0;
                    $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : 0;
                    if (! is_null($lunchBreak)) {
                        $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                    }
                    if (! is_null($breakTime)) {
                        $breakTime = gmdate('H:i:s', $breakTime * 60);
                    }
                    // need to minus lunch and break time
                    $lunchDuration = Carbon::parse($lunchBreak)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                    $breakDuration = Carbon::parse($breakTime)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
                    $totalWorkedSeconds = $diffInSeconds - ($lunchDuration + $breakDuration);
                    $finalTimeDifference = gmdate('H:i:s', $totalWorkedSeconds);
                    $attendanceData = [];
                    $attendanceData['id'] = $get_request->id;
                    $attendanceData['user_id'] = $get_request->user_id;
                    $attendanceData['current_time'] = $finalTimeDifference;
                    $attendanceData['lunch_time'] = $lunchBreak;
                    $attendanceData['break_time'] = $breakTime;
                    $attendanceData['date'] = $get_request->adjustment_date;
                    if (! empty($attendanceData['current_time'])) {
                        $s = $this->payroll_wages_create($attendanceData);
                        // dd($s);
                    }
                    // dd($s);
                } else {
                    $check_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                        ->where('start_date', '<=', $schedule_from_date)
                        ->where('end_date', '>=', $schedule_from_date)
                        ->where('adjustment_type_id', 8)
                        ->where('status', 'Approved')
                        ->first();
                    if (! empty($check_pto)) {
                        $user_checkin = $schedule_from_date.' 08:00:00';
                        // $user_checkout = $schedule_from_date." 08:00:00";
                        $clockIn = Carbon::parse($user_checkin);
                        if (! empty($check_pto) && isset($check_pto->pto_hours_perday) && ! is_null($check_pto->pto_hours_perday)) {
                            $newClockOut = Carbon::parse($user_checkin);
                            $clockOut = $newClockOut->addHours($check_pto->pto_hours_perday);
                        } else {
                            $clockOut = Carbon::parse($user_checkin);
                        }
                        // Calculate the difference in seconds
                        $diffInSeconds = $clockIn->diffInSeconds($clockOut);
                        $timeDifference = gmdate('H:i:s', $diffInSeconds);
                        $attendanceData = [];
                        $attendanceData['id'] = $check_pto->id;
                        $attendanceData['user_id'] = $check_pto->user_id;
                        $attendanceData['current_time'] = $timeDifference;
                        $attendanceData['lunch_time'] = '00:00:00';
                        $attendanceData['break_time'] = '00:00:00';
                        $attendanceData['date'] = $schedule_from_date;
                        if (! empty($attendanceData['current_time'])) {
                            $s = $this->payroll_wages_create($attendanceData);
                            if (! empty($s)) {
                                foreach ($s as $key => $val) {
                                    if (! in_array($key, $addedUserIds)) {
                                        $closedUsers .= $val.', ';
                                        $addedUserIds[] = $key;
                                    }
                                }
                                $closedUsers = rtrim($closedUsers, ', ');
                            }
                        }
                    }
                }
                $check_attendance = UserAttendance::where(['user_id' => $schedule->user_id, 'date' => $schedule_from_date])->first();
                if (empty($check_attendance)) {
                    $message = 'You cannot approve timesheets for a pay period that is already closed.';

                    return response()->json([
                        'ApiName' => 'userAttendenceAppoval',
                        'status' => true,
                        'message' => $message,
                    ], 400);
                }
            }
        }
        $message = "User's attendance status successfully updated. ";
        if (! empty($closedUsers)) {
            $message = 'You cannot approve timesheets for a pay period that is already closed.';

            return response()->json([
                'ApiName' => 'userAttendenceAppoval',
                'status' => true,
                'message' => $message,
            ], 400);
        }

        return response()->json([
            'ApiName' => 'userAttendenceAppoval',
            'status' => true,
            'message' => $message,
        ], 200);
    }

    public function update_attendance_status_schedules_details($schedule)
    {
        $getData = UserScheduleDetail::findOrFail($schedule->id);
        if (! empty($getData)) {
            $getData->attendance_status = 1;
            $getData->save();

            return $getData->id;
        }

        return false;
    }

    public function update_attendance_id_schedules_details($schedule, $user_attendence)
    {
        $getData = UserScheduleDetail::findOrFail($schedule);
        if (! empty($getData)) {
            $getData->user_attendance_id = $user_attendence->id;
            $getData->save();

            return $getData->id;
        }

        return false;
    }

    public function payroll_wages_create($data)
    {
        $userId = $data['user_id'];
        $totalHours = $data['current_time'];
        $date = $data['date'];
        $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'desc')->first();
        if ($userWagesHistory && !empty($userWagesHistory->pay_type) && !empty($userWagesHistory->pay_rate)) {
            $user = User::find($userId);
            $subPositionId = $user->sub_position_id;
            $stop_payroll = $user->stop_payroll;
            $payFrequency = $this->payFrequency($date, $subPositionId, $user->id);
            if ($payFrequency && $payFrequency->closed_status == 0) {
                $overtimeRate = isset($userWagesHistory->overtime_rate) ? $userWagesHistory->overtime_rate : 0;
                $payRate = isset($userWagesHistory->pay_rate) ? $userWagesHistory->pay_rate : 0;
                $sDate = $payFrequency->pay_period_from;
                $eDate = $payFrequency->pay_period_to;
                if ($userWagesHistory->pay_type == 'Salary') {
                    $timeString = $data['current_time'];
                    list($hours, $minutes, $seconds) = explode(':', $timeString);
                    $totalMinutes = floor(($hours * 60) + $minutes + ($seconds / 60));
                    if ($totalMinutes > 0) {
                        // $totalHours = number_format(($totalMinutes / 60), 2);
                        // if ($totalHours > 8) {
                        //     $hourTotal = 8;
                        //     $formattedTime = "08:00";
                        // } else {
                        //     $hourTotal = $totalHours;
                        //     $dateTime = new DateTime($timeString);
                        //     $formattedTime = $dateTime->format('H:i');
                        // }
                        $totalRate = $userWagesHistory->pay_rate;
                        $payrollHourlySalary = PayrollHourlySalary::where(['user_id' => $userId, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->where('status', '!=', 3)->first();
                        if ($payrollHourlySalary) {
                            $payrollHourlySalary->user_id = $userId;
                            $payrollHourlySalary->position_id = $subPositionId;
                            $payrollHourlySalary->date = $date;
                            // $payrollHourlySalary->hourly_rate = $userWagesHistory->pay_rate;
                            $payrollHourlySalary->salary = isset($totalRate) ? $totalRate : 0;
                            // $payrollHourlySalary->regular_hours = $formattedTime;
                            $payrollHourlySalary->total = isset($totalRate) ? $totalRate : 0;
                            $payrollHourlySalary->pay_period_from = isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null;
                            $payrollHourlySalary->pay_period_to = isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null;
                            $payrollHourlySalary->status = 1;
                            $payrollHourlySalary->is_stop_payroll = $stop_payroll;
                            $payrollHourlySalary->pay_frequency = $payFrequency->pay_frequency;
                            $payrollHourlySalary->user_worker_type = $user->worker_type;
                            $payrollHourlySalary->save();

                            $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id' => $payrollHourlySalary->payroll_id, 'type' => 'hourlysalary', 'payroll_type' => 'hourlysalary'])->first();
                            if ($payrollAdjustmentDetail) {
                                $payrollAdjustmentDetail->salary_overtime_date = $date;
                                $payrollAdjustmentDetail->save();
                            }
                        } else {
                            PayrollHourlySalary::create([
                                'user_id' => $userId,
                                'position_id' => $subPositionId,
                                'date' => $date,
                                // 'hourly_rate' => $userWagesHistory->pay_rate,
                                'salary' => isset($totalRate) ? $totalRate : 0,
                                // 'regular_hours' => isset($formattedTime) ? $formattedTime : 0,
                                'total' => isset($totalRate) ? $totalRate : 0,
                                'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                                'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                                'status' => 1,
                                'is_stop_payroll' => $stop_payroll,
                                'pay_frequency' => $payFrequency->pay_frequency,
                                'user_worker_type' => $user->worker_type
                            ]);
                        }
                    }
                } else {
                    $timeString = $data['current_time'];
                    list($hours, $minutes, $seconds) = explode(':', $timeString);
                    $totalMinutes = floor(($hours * 60) + $minutes + ($seconds / 60));
                    if ($totalMinutes > 0) {
                        $totalHours = number_format(($totalMinutes / 60), 2);
                        if ($totalHours > 8) {
                            $hourTotal = 8;
                            $formattedTime = "08:00";
                        } else {
                            $hourTotal = $totalHours;
                            $dateTime = new DateTime($timeString);
                            $formattedTime = $dateTime->format('H:i');
                        }
                        $totalRate = ($hourTotal * $userWagesHistory->pay_rate);

                        $payrollHourlySalary = PayrollHourlySalary::where(['user_id' => $userId, 'date' => $date, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->where('status', '!=', 3)->first();
                        if ($payrollHourlySalary) {
                            $payrollHourlySalary->user_id = $userId;
                            $payrollHourlySalary->position_id = $subPositionId;
                            $payrollHourlySalary->date = $date;
                            $payrollHourlySalary->hourly_rate = $userWagesHistory->pay_rate;
                            $payrollHourlySalary->salary = isset($totalRate) ? $totalRate : 0;
                            $payrollHourlySalary->regular_hours = $formattedTime;
                            $payrollHourlySalary->total = isset($totalRate) ? $totalRate : 0;
                            $payrollHourlySalary->pay_period_from = isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null;
                            $payrollHourlySalary->pay_period_to = isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null;
                            $payrollHourlySalary->status = 1;
                            $payrollHourlySalary->is_stop_payroll = $stop_payroll;
                            $payrollHourlySalary->pay_frequency = $payFrequency->pay_frequency;
                            $payrollHourlySalary->user_worker_type = $user->worker_type;
                            $payrollHourlySalary->save();
                        } else {
                            PayrollHourlySalary::create([
                                'user_id' => $userId,
                                'position_id' => $subPositionId,
                                'date' => $date,
                                'hourly_rate' => $userWagesHistory->pay_rate,
                                'salary' => isset($totalRate) ? $totalRate : 0,
                                'regular_hours' => isset($formattedTime) ? $formattedTime : 0,
                                'total' => isset($totalRate) ? $totalRate : 0,
                                'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                                'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                                'status' => 1,
                                'is_stop_payroll' => $stop_payroll,
                                'pay_frequency' => $payFrequency->pay_frequency,
                                'user_worker_type' => $user->worker_type
                            ]);
                        }

                        if ($totalHours > 8 && $overtimeRate > 0) {
                            $payFrequency = $payFrequency->pay_frequency;
                            $userWorkerType = $user->worker_type;
                            $this->payrollOvertimeForHourly($userId, $subPositionId, $sDate, $eDate, $payFrequency, $userWorkerType, $overtimeRate, $date, $stop_payroll, $totalHours, $payRate);
                        }
                    }
                }
            } else {
                if ($payFrequency && $payFrequency->closed_status == 1) {
                    $userName = $user->first_name . ' ' . $user->last_name;
                    $userId = $user->id;
                    $userArray[$userId] = $userName;
                    return $userArray;
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

    protected function payrollOvertimeForHourly($userId, $subPositionId, $sDate, $eDate, $payFrequency, $userWorkerType, $overtimeRate, $date, $stop_payroll, $totalHours, $payRate)
    {
        if ($totalHours > 8) {
            $overtime = ($totalHours - 8);
            $totals = (($totalHours - 8) * $overtimeRate * $payRate);
            $overtimeRates = ($overtimeRate * $payRate);

            $hours = floor($overtime);
            $minutes = round(($overtime - $hours) * 60);
            $overtimeHours = sprintf("%02d:%02d", $hours, $minutes);

            $payrollOvertime = PayrollOvertime::where(['user_id' => $userId, 'date' => $date, 'pay_period_from' => $sDate, 'pay_period_to' => $eDate])->where('status', '!=', 3)->first();
            if ($payrollOvertime) {
                $payrollOvertime->user_id = $userId;
                $payrollOvertime->position_id = $subPositionId;
                $payrollOvertime->date = $date;
                $payrollOvertime->overtime_rate = $overtimeRates;
                $payrollOvertime->overtime = $overtime;
                $payrollOvertime->overtime_hours = $overtimeHours;
                $payrollOvertime->total = $totals;
                $payrollOvertime->pay_period_from = isset($sDate) ? $sDate : null;
                $payrollOvertime->pay_period_to = isset($eDate) ? $eDate : null;
                $payrollOvertime->status = 1;
                $payrollOvertime->is_stop_payroll = $stop_payroll;
                $payrollOvertime->pay_frequency = $payFrequency;
                $payrollOvertime->user_worker_type = $userWorkerType;
                $payrollOvertime->save();
            } else {
                PayrollOvertime::create([
                    'user_id' => $userId,
                    'position_id' => $subPositionId,
                    'date' => $date,
                    'overtime_rate' => $overtimeRates,
                    'overtime' => $overtime,
                    'overtime_hours' => $overtimeHours,
                    'total' => $totals,
                    'pay_period_from' => isset($sDate) ? $sDate : null,
                    'pay_period_to' => isset($eDate) ? $eDate : null,
                    'status' => 1,
                    'is_stop_payroll' => $stop_payroll,
                    'pay_frequency' => $payFrequency,
                    'user_worker_type' => $userWorkerType
                ]);
            }
        }
    }

    public function getFinalizeStatus($schedule)
    {
        // dd($schedule->user_id);
        $userId = $schedule->user_id;
        $userData = User::findOrFail($userId);
        $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
        // $schedule_from_date = "2024-07-22";
        $positionId = $userData->position_id;
        $sub_positionId = $userData->sub_position_id;
        // dd($positionId,$userId, $sub_positionId);
        // $frequencyTypeData  = null;
        if (! empty($sub_positionId)) {
            $pay_frequency_id = PositionPayFrequency::where('position_id', $sub_positionId)->select('frequency_type_id')->first();
            if (! empty($pay_frequency_id)) {
                $frequencyTypeData = FrequencyType::find($pay_frequency_id->frequency_type_id);
            }
        } else {
            $pay_frequency_id2 = PositionPayFrequency::where('position_id', $positionId)->select('frequency_type_id')->first();
            // dd($pay_frequency_id2->frequency_type_id);
            if (! empty($pay_frequency_id2)) {
                $frequencyTypeData = FrequencyType::find($pay_frequency_id2->frequency_type_id);
            }
        }
        $data['frequency'] = $frequencyTypeData->name ?? null;
        $query = Payroll::with('usersdata', 'positionDetail')
            ->where('status', '=', 2)
            // ->where(function ($q) {
            //     $q->where('finalize_status', '=', 0)
            //     ->orWhere('finalize_status', '=', 3);
            // })
            ->where('user_id', $userId)
            ->whereDate('pay_period_from', '<=', $schedule_from_date)
            ->whereDate('pay_period_to', '>=', $schedule_from_date)
            ->first();
        // dd($query);
        $payrollHistory = PayrollHistory::where('status', '=', 3)
            ->where('user_id', $userId)
            ->whereDate('pay_period_from', '<=', $schedule_from_date)
            ->whereDate('pay_period_to', '>=', $schedule_from_date)
            ->first();
        $finalizeStatus = ! empty($query) ? true : false;
        $executeStatus = ! empty($payrollHistory) ? true : false;
        if ($finalizeStatus) {
            $data['finalizeStatus'] = true;
        } else {
            $data['finalizeStatus'] = $executeStatus;
        }

        // $data['finalizeStatus']   = $finalizeStatus;
        $data['executeStatus'] = $executeStatus;

        return $data;

    }

    public function createUserSchedule_old_method(CreateUserScheduleRequest $request)
    {
        // return $request->all();
        $userIds = $request->user_id;
        $schedules = $request->schedules;
        // $office_id  = $request->office_id;
        $is_flexible = $request->is_flexible;
        $is_repeat = $request->is_repeat;
        $gnextWeekStartDate = $this->getCurrentWeekStartDate1();
        $nextWeekStartDate = Carbon::parse($gnextWeekStartDate);
        // return [$gnextWeekStartDate,$nextWeekStartDate];
        $currentDate = Carbon::now();
        DB::beginTransaction();
        try {
            // Adjust to include Sunday to Saturday
            // $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday','Sunday'];
            $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            foreach ($userIds as $uid) {
                $existingSchedules = [];
                if ($request->has('office_id') && $request->office_id != 'all') {
                    $office_id = $request->office_id;
                } else {
                    $user_data = User::find($uid);
                    if (! empty($user_data) && isset($user_data->office_id) && ! empty($user_data->office_id)) {
                        $office_id = $user_data->office_id;
                    }
                }
                // return $office_id;
                foreach ($schedules as $schedule) {
                    $scheduleDate = $nextWeekStartDate->copy()->addDays($schedule['work_days'] - 1)->toDateString();
                    $dayOfWeek = date('l', strtotime($scheduleDate));
                    $dayNumberOfWeek = date('N', strtotime($scheduleDate));
                    // return [ $scheduleDate, $dayOfWeek, $dayNumberOfWeek];
                    $date1 = Carbon::createFromFormat('Y-m-d H:i:s', $schedule['schedule_from']);
                    $newScheduleDate = $formattedDate = $date1->format('Y-m-d');
                    // return $newScheduleDate;
                    // Check if the schedule already exists
                    $checkUserScheduleData = UserSchedule::where('user_id', $uid)->first();
                    if (empty($checkUserScheduleData)) {
                        $userScheduleObj = new UserSchedule;
                        $userScheduleObj->user_id = $uid;
                        $userScheduleObj->scheduled_by = Auth::user()->id;
                        $userScheduleObj->is_flexible = $is_flexible;
                        $userScheduleObj->is_repeat = $is_repeat;
                        $userScheduleObj->save();
                        $schedule_id = $userScheduleObj->id;
                    } else {
                        $schedule_id = $checkUserScheduleData->id;
                    }

                    $checkUserScheduleDetailsData = UserScheduleDetail::where('schedule_id', $schedule_id)
                        ->where('office_id', $office_id)
                        ->whereDate('schedule_from', $newScheduleDate)
                        ->whereDate('schedule_to', $newScheduleDate)
                        // ->where('work_days', $dayNumberOfWeek)
                        ->where('work_days', $schedule['work_days'])
                        ->exists();

                    if ($checkUserScheduleDetailsData) {
                        DB::rollBack();

                        return response()->json(['message' => 'A schedule already exists, no data was inserted.'], 400);
                    }
                    $existingSchedules[$schedule['work_days_name']][] = [
                        // $existingSchedules[$dayOfWeek][] = [
                        'office_id' => $office_id,
                        'schedule_id' => $schedule_id,
                        'lunch_duration' => (isset($schedule['lunch_duration']) && ! empty($schedule['lunch_duration'])) ? $schedule['lunch_duration'] : null,
                        // 'schedule_from' => $scheduleDate . ' ' . $schedule['schedule_from'],
                        // 'schedule_to' => $scheduleDate . ' ' . $schedule['schedule_to'],
                        'schedule_from' => $schedule['schedule_from'],
                        'schedule_to' => $schedule['schedule_to'],
                        // 'work_days' => $dayNumberOfWeek,
                        // 'work_days' => $dayOfWeek,
                        'work_days' => $schedule['work_days'],
                        'is_flexible' => $is_flexible,
                        'repeated_batch' => 0,
                        'updated_type' => null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ];

                }
                // return $existingSchedules;

                foreach ($existingSchedules as $daySchedules) {
                    // return $daySchedules;
                    foreach ($daySchedules as $daySchedule) {
                        // return $daySchedule;
                        $checkExists = UserScheduleDetail::where('schedule_id', $schedule_id)
                            ->where('office_id', $office_id)
                            ->whereDate('schedule_from', $newScheduleDate)
                            ->whereDate('schedule_to', $newScheduleDate)
                            // ->where('work_days', $dayNumberOfWeek)
                            ->where('work_days', $schedule['work_days'])
                            ->exists();

                        if (! $checkExists) {
                            UserScheduleDetail::insertOrIgnore($daySchedule);
                        }
                    }
                }

                foreach ($daysOfWeek as $day) {
                    if (! isset($existingSchedules[$day])) {
                        $weekDates = $this->getUserCurrentWeekStartAndEndDates1();
                        $day_no = config('constant.scheduling.day_no');
                        $result = array_flip($day_no);
                        $work_days = $result[$day];
                        $nextDate = $this->getUserNextDateForDay1($day, $work_days, $weekDates);
                        UserScheduleDetail::insert([
                            'schedule_id' => $schedule_id,
                            'office_id' => $office_id,
                            'lunch_duration' => null,
                            'schedule_from' => $nextDate.' 00:00:00',
                            'schedule_to' => $nextDate.' 00:00:00',
                            'work_days' => $work_days,
                            'repeated_batch' => 0,
                            'updated_type' => null,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'ApiName' => 'createScheduling',
                'status' => true,
                'message' => 'Schedules created successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'createScheduling',
                'status' => false,
                'message' => 'An error occurred while creating schedules',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    private function getCurrentWeekStartDate1()
    {
        return Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();
    }

    private function getUserCurrentWeekStartAndEndDates1()
    {
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::SUNDAY);
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SATURDAY);

        return [
            'start_date' => $startOfWeek->toDateString(),
            'end_date' => $endOfWeek->toDateString(),
        ];
    }

    private function getUserNextDateForDay1($day, $work_days, $weekDates)
    {
        $startOfWeek = Carbon::parse($weekDates['start_date']);

        return $startOfWeek->addDays($work_days - 1)->toDateString();
    }

    public function getCalTotalWorkedHour($hours, $minutes)
    {
        // $hours = 10;
        // $minutes = 90;

        // Add the minutes to the hours
        $totalMinutes = ($hours * 60) + $minutes;

        // Convert total minutes back to hours and minutes
        $totalHours = floor($totalMinutes / 60);
        $remainingMinutes = $totalMinutes % 60;

        // Create a Carbon instance and format it to H:i:s
        $timeFormatted = Carbon::createFromTime($totalHours, $remainingMinutes)->format('H:i:s');

        // echo "-----> ".$timeFormatted;
        return $timeFormatted;
    }

    public function calculateGetDiffWorkedHours($userDataWorkedHours, $totalLunchBreakTime)
    {
        $workedHours = Carbon::parse($userDataWorkedHours);
        $totalLunchBreakTime = Carbon::parse($totalLunchBreakTime);

        // Calculate the difference
        $netWorkedTime = $workedHours->diff($totalLunchBreakTime)->format('%H:%I:%S');

        return $netWorkedTime;
    }
    // replace new method for creating shedules

    public function createUserSchedule(CreateUserScheduleRequest $request)
    {
        // return $request->all();
        $userIds = $request->user_id;
        $schedules = $request->schedules;
        $is_continue = isset($request->is_continue) ? $request->is_continue : false;
        // dd($is_continue);
        // $office_id  = $request->office_id;
        $is_flexible = $request->is_flexible;
        $is_repeat = $request->is_repeat;
        $gnextWeekStartDate = $this->getCurrentWeekStartDate1();
        $nextWeekStartDate = Carbon::parse($gnextWeekStartDate);
        // return [$gnextWeekStartDate,$nextWeekStartDate];
        $currentDate = Carbon::now();
        DB::beginTransaction();
        try {
            // Adjust to include Sunday to Saturday
            // $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday','Sunday'];
            $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $message = 'Schedules created successfully';

            $lastSchedule = array_values($schedules)[count($schedules) - 1];
            foreach ($userIds as $userID) {
                $contractEnded = checkContractEndFlag($userID, $lastSchedule);
                if ($contractEnded) {
                    $user = User::find($userID);

                    return response()->json([
                        'ApiName' => 'add-request',
                        'status' => true,
                        'message' => $user?->first_name.' '.$user?->last_name.' contract endes at '.$contractEnded->end_date.' can not be schedule further then the end date.',
                    ], 400);
                }

                $terminated = checkTerminateFlag($userID, $lastSchedule);
                if ($terminated && $terminated->is_terminate) {
                    $user = User::find($userID);

                    return response()->json([
                        'ApiName' => 'add-request',
                        'status' => true,
                        'message' => $user?->first_name.' '.$user?->last_name.' is/will be terminated at '.$terminated->endterminate_effective_date_date.' can not be schedule further then the termination date.',
                    ], 400);
                }

                $dismissed = checkDismissFlag($userID, $lastSchedule);
                if ($dismissed && $dismissed->dismiss) {
                    $user = User::find($userID);

                    return response()->json([
                        'ApiName' => 'add-request',
                        'status' => true,
                        'message' => $user?->first_name.' '.$user?->last_name.' is/will be disabled at '.$dismissed->effective_date.' can not be schedule further then the disabled date.',
                    ], 400);
                }
            }

            foreach ($userIds as $uid) {
                $existingSchedules = [];
                if ($request->has('office_id') && $request->office_id != 'all') {
                    $office_id = $request->office_id;
                } else {
                    $user_data = User::find($uid);
                    if (! empty($user_data) && isset($user_data->office_id) && ! empty($user_data->office_id)) {
                        $office_id = $user_data->office_id;
                    }
                }
                // return $office_id;
                $scheduleFromDates = array_column($schedules, 'schedule_from');
                $scheduleToDates = array_column($schedules, 'schedule_to');
                $week_startDate = min($scheduleFromDates);
                $week_endDate = max($scheduleToDates);
                $week_startDate = Carbon::parse($week_startDate)->format('Y-m-d');
                $week_endDate = Carbon::parse($week_endDate)->format('Y-m-d');

                $checkUserScheduleData = UserSchedule::where('user_id', $uid)->first();
                if (empty($checkUserScheduleData)) {
                    $userScheduleObj = new UserSchedule;
                    $userScheduleObj->user_id = $uid;
                    $userScheduleObj->scheduled_by = Auth::user()->id;
                    $userScheduleObj->is_flexible = $is_flexible;
                    $userScheduleObj->is_repeat = $is_repeat;
                    $userScheduleObj->save();
                    $schedule_id = $userScheduleObj->id;
                } else {
                    $checkUserScheduleData->is_repeat = $is_repeat;
                    $checkUserScheduleData->save();
                    $schedule_id = $checkUserScheduleData->id;
                }

                $check_existingSchedules = UserScheduleDetail::where('schedule_id', $schedule_id)
                    ->whereBetween('schedule_from', [$week_startDate, $week_endDate])
                    ->exists();
                // dd($check_existingSchedules);
                if ($check_existingSchedules && $is_continue == false) {
                    return response()->json(['status' => true, 'is_exists' => true, 'message' => 'A schedule already exists, Do you want to continue to update existing scheduless?'], 200);
                }
                // dd('okk');
                foreach ($schedules as $schedule) {
                    $scheduleDate = $nextWeekStartDate->copy()->addDays($schedule['work_days'] - 1)->toDateString();
                    $dayOfWeek = date('l', strtotime($scheduleDate));
                    $dayNumberOfWeek = date('N', strtotime($scheduleDate));
                    // return [ $scheduleDate, $dayOfWeek, $dayNumberOfWeek];
                    $date1 = Carbon::createFromFormat('Y-m-d H:i:s', $schedule['schedule_from']);
                    $newScheduleDate = $formattedDate = $date1->format('Y-m-d');
                    // return $newScheduleDate;
                    // Check if the schedule already exists
                    $checkUserScheduleData = UserSchedule::where('user_id', $uid)->first();
                    if (empty($checkUserScheduleData)) {
                        $userScheduleObj = new UserSchedule;
                        $userScheduleObj->user_id = $uid;
                        $userScheduleObj->scheduled_by = Auth::user()->id;
                        $userScheduleObj->is_flexible = $is_flexible;
                        $userScheduleObj->is_repeat = $is_repeat;
                        $userScheduleObj->save();
                        $schedule_id = $userScheduleObj->id;
                    } else {
                        $schedule_id = $checkUserScheduleData->id;
                    }

                    $existingScheduleDetail = UserScheduleDetail::where('schedule_id', $schedule_id)
                        ->where('office_id', $office_id)
                        ->where('work_days', $schedule['work_days'])
                        ->whereDate('schedule_from', $newScheduleDate)
                        ->first();

                    $scheduleData = [
                        'office_id' => $office_id,
                        'schedule_id' => $schedule_id,
                        'lunch_duration' => $schedule['lunch_duration'] ?? null,
                        'schedule_from' => $schedule['schedule_from'],
                        'schedule_to' => $schedule['schedule_to'],
                        'work_days' => $schedule['work_days'],
                        'is_flexible' => $is_flexible,
                        'repeated_batch' => 0,
                        'updated_type' => null,
                        'created_at' => now(),
                    ];
                    if ($existingScheduleDetail) {
                        // Update the existing schedule
                        $existingScheduleDetail->update($scheduleData);
                    } else {
                        // Create a new schedule
                        UserScheduleDetail::create($scheduleData);
                    }
                    // if($is_repeat == 1){
                    //     // dd(1333);
                    //     $res = GenerateUserSchedulesRepeatJob::dispatch($uid, $is_repeat);
                    // }

                }
                // if($is_repeat == 1){
                //     // dd(1333);
                //     $res = GenerateUserSchedulesRepeatJob::dispatch($uid, $is_repeat);
                // }
                // DB::commit();
                // return $existingSchedules;

            }
            if ($is_repeat == 1) {
                // dd(1333);
                $message = "Your 3-month schedules are in progress. Once it's completed, we will send you a notification message.";
                $res = GenerateUserSchedulesRepeatJob::dispatch($uid, $is_repeat);
            }
            DB::commit();

            return response()->json([
                'ApiName' => 'createScheduling',
                'status' => true,
                // 'message' => 'Schedules created successfully',
                'message' => $message,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'createScheduling',
                'status' => false,
                'message' => 'An error occurred while creating schedules',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function convertTimeToSeconds($time)
    {
        [$hours, $minutes, $seconds] = explode(':', $time);

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    public function convertSecondsToTime($totalSeconds)
    {
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function isValidExtendedTime($time)
    {
        return preg_match('/^([0-9]+):[0-5][0-9]:[0-5][0-9]$/', $time);
    }

    private function getFutureWeekStartAndEndDates($weeksAhead)
    {
        $currentDate = Carbon::now();

        // Calculate the start of the future week
        $futureWeekStartDate = $currentDate->copy()->addWeeks($weeksAhead)->startOfWeek(Carbon::SUNDAY);

        // Calculate the end of the future week
        $futureWeekEndDate = $futureWeekStartDate->copy()->endOfWeek(Carbon::SATURDAY);

        return [
            'next_week_start_date' => $futureWeekStartDate->toDateString(),
            'next_week_end_date' => $futureWeekEndDate->toDateString(),
        ];
    }

    private function currentWeekDate()
    {
        $currentDate = Carbon::now();
        // Clone the date to avoid modifying the original instance
        $currentWeekStart = $currentDate->copy()->startOfWeek(Carbon::SUNDAY);
        $currentWeekEnd = $currentDate->copy()->endOfWeek(Carbon::SATURDAY);

        // Display the dates
        // echo "Start of the Week: " . $currentWeekStart->toDateString(); // e.g., 2024-04-21
        // echo "\nEnd of the Week: " . $currentWeekEnd->toDateString();     // e.g., 2024-04-27
        return ['currentWeekStart' => $currentWeekStart->toDateString(), 'currentWeekEnd' => $currentWeekEnd->toDateString()];
    }

    private function createOrUpdateUserSchedules($user_id, $office_id, $clock_in, $clock_out, $adjustment_date, $lunch)
    {
        // dd($user_id, $office_id,$clock_in,$clock_out,$adjustment_date,$lunch);
        if (! empty($lunch) && ! is_null($lunch) && $lunch != 'None') {
            $lunch = $lunch.' Mins';
        }
        $userschedule = UserSchedule::where('user_id', $user_id)->first();
        // dd($userschedule);
        $s_date = Carbon::parse($clock_in);
        $dayNumber = $s_date->dayOfWeekIso;
        if ($userschedule) {
            $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id', $userschedule->id)->where('office_id', $office_id)->wheredate('schedule_from', $adjustment_date)->first();
            if (empty($checkUserScheduleDetail) || $checkUserScheduleDetail == null) {
                $scheduleDetaisData = [
                    'schedule_id' => $userschedule->id,
                    'office_id' => $office_id,
                    'schedule_from' => $clock_in,
                    // 'schedule_to' => $scheduleTo->toDateTimeString(),
                    'schedule_to' => $clock_out,
                    'lunch_duration' => $lunch,
                    'work_days' => $dayNumber,
                    'repeated_batch' => 0,
                    'user_attendance_id' => null,
                ];
                $dataStored = UserScheduleDetail::create($scheduleDetaisData);
            } else {
                // update schedules
                // $checkUserScheduleDetail->where('schedule_id',$userschedule->id)->where('office_id',$office_id)->wheredate('schedule_from',$adjustment_date)->update(['schedule_from' => $clock_in, 'schedule_to' => $clock_out,'lunch_duration' => $lunch, 'work_days' => $dayNumber]);
            }
        } else {
            $create_userschedule = UserSchedule::create(['user_id' => $request->user_id, 'scheduled_by' => Auth::user()->id]);
            if ($create_userschedule) {
                $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id', $create_userschedule->id)
                    ->where('office_id', $office_id)
                    ->wheredate('schedule_from', $request->adjustment_date)
                    ->first();
                if (empty($checkUserScheduleDetail) || $checkUserScheduleDetail == null) {
                    $scheduleDetaisData = [
                        'schedule_id' => $create_userschedule->id,
                        'office_id' => $office_id,
                        'schedule_from' => $clock_in,
                        'schedule_to' => $clock_out,
                        'lunch_duration' => null,
                        'work_days' => $dayNumber,
                        'repeated_batch' => 0,
                        'user_attendance_id' => null,
                    ];
                    $dataStored = UserScheduleDetail::create($scheduleDetaisData);
                }
            }
        }
    }
}
