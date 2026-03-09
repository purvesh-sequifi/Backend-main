<?php

namespace App\Exports;

use App\Models\ApprovalsAndRequest;
use App\Models\UserAttendance;
use App\Models\UserAttendanceDetail;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SchedulesExport implements FromCollection, WithHeadings
{
    private $startDate;

    private $endDate;

    private $location;

    public function __construct($location = '', $startDate = '', $endDate = '')
    {
        $this->location = $location;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        if ($this->location != '' && $this->startDate != '' && $this->endDate != '') {
            if ($this->location != 'all') {
                $userSchedulesData = UserSchedule::select(
                    'users.id as user_id',
                    'users.first_name',
                    'users.middle_name',
                    'users.last_name',
                    'user_schedules.is_flexible',
                    'user_schedules.is_repeat',
                    'user_schedule_details.lunch_duration',
                    'user_schedule_details.schedule_from',
                    'user_schedule_details.schedule_to',
                    'user_schedule_details.work_days',
                    'user_schedule_details.office_id',
                    'user_schedule_details.user_attendance_id',
                    'user_schedule_details.attendance_status'
                )
                    ->join('users', 'user_schedules.user_id', '=', 'users.id')
                    ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                    ->whereBetween('user_schedule_details.schedule_from', [$this->startDate, $this->endDate])
                    ->where('user_schedule_details.office_id', $this->location)
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
                    'user_schedule_details.lunch_duration',
                    'user_schedule_details.schedule_from',
                    'user_schedule_details.schedule_to',
                    'user_schedule_details.work_days',
                    'user_schedule_details.office_id',
                    'user_schedule_details.user_attendance_id',
                    'user_schedule_details.attendance_status'
                )
                    ->join('users', 'user_schedules.user_id', '=', 'users.id')
                    ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                    ->whereBetween('user_schedule_details.schedule_from', [$this->startDate, $this->endDate])
                       // ->where('user_schedule_details.office_id',$this->location)
                    ->orderBy('users.id')
                    ->get();
            }

            // Format the data as required
            // return $userSchedulesData;
            $formattedData = [];
            $req_approvals_leave = '';
            foreach ($userSchedulesData as $schedule) {
                $dayName = Carbon::parse($schedule->schedule_from)->format('l');
                $dayNumber = Carbon::parse($schedule->schedule_from)->format('N');
                $timeDifference = $this->calculateTimeDifference($schedule->schedule_from, $schedule->schedule_to);
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
                    ->first();

                $req_approvals_leave = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                    ->where('start_date', '<=', $schedule_from_date)
                    ->where('end_date', '>=', $schedule_from_date)
                    ->where('adjustment_type_id', 7)
                    ->first();
                $user_attendence = UserAttendance::where('user_id', $schedule->user_id)
                    ->where('date', $schedule_from_date)
                    ->first();
                $user_checkin = null;
                $user_checkout = null;
                $is_present = false;
                $is_late = false;
                $user_attendence_status = false;
                if (! empty($user_attendence)) {
                    $user_attendence_status = ($schedule->attendance_status) ? true : false;
                    // $is_present = true;
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
                            if (! empty($user_checkout_obj)) {
                                $user_checkout = $user_checkout_obj->attendance_date;
                            } else {
                                $user_checkout = null;
                            }
                        } else {
                            $user_checkin = null;
                            $user_checkout = null;
                        }
                        if (! empty($user_checkin) && ! empty($user_checkout)) {
                            $checkInTimeDifference = '10 Hours 10 Minutes';
                            // $checkInTimeDifference = $this->calculateTimeDifference($user_checkin, $user_checkout);
                        } else {
                            $checkInTimeDifference = '00 Hours 00 Minutes';
                        }
                    }

                    $is_late = $this->compareDateTime($user_checkin, $schedule->schedule_from);
                } else {
                    $user_attendence_status = ($schedule->attendance_status) ? true : false;
                    $req_approvals_data = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                        ->where('adjustment_date', '=', $schedule_from_date)
                        ->where('adjustment_type_id', 9)
                        ->where('status', 'Approved')
                        ->first();
                    // echo $schedule_from_date;echo '<pre>';
                    if (! empty($req_approvals_data)) {
                        $user_checkin = isset($req_approvals_data) ? $req_approvals_data->clock_in : null;
                        $user_checkout = isset($req_approvals_data) ? $req_approvals_data->clock_out : null;
                    } else {
                        $user_checkin = null;
                        $user_checkout = null;
                    }

                    $checkInTimeDifference = $this->calculateTimeDifference($user_checkin, $user_checkout);
                }
                // return [$req_approvals_pto,$req_approvals_leave];
                // $formattedData[$schedule->user_id]['user_id'] = $schedule->user_id;
                // $formattedData[$schedule->user_id]['user_name'] = $schedule->first_name . ' ' . ($schedule->middle_name ?? '') . ' ' . $schedule->last_name;
                // $formattedData[$schedule->user_id]['schedules'][$dayName] = [
                //     'lunch_duration' => $schedule->lunch_duration,
                //     'schedule_from' => $schedule->schedule_from,
                //     'schedule_to' => $schedule->schedule_to,
                //     'work_days' => $dayNumber,
                //     'day_name' => $dayName,
                //     'clock_hours' => $timeDifference,
                //     'checkPTO' => !empty($req_approvals_pto) ? $req_approvals_pto->pto_per_day : false,
                //     'checkLeave' => !empty($req_approvals_leave) ? true : false,
                // ];
                $formattedData[] = [
                    'UserId' => $schedule->user_id,
                    'FirstName' => $schedule->first_name ?? '',
                    'MiddleName' => $schedule->middle_name ?? '',
                    'LastName' => $schedule->last_name ?? '',
                    'LunchDuration' => $schedule->lunch_duration,
                    // 'lunch_duration'   => $schedule->lunch_duration,
                    'ScheduleFrom' => $schedule->schedule_from,
                    'ScheduleTo' => $schedule->schedule_to,
                    'WorkDays' => $dayNumber,
                    'IsAvailable' => $is_available ? 'Yes' : 'No',
                    'DayName' => $dayName,
                    'ClockHours' => $timeDifference,
                    'IsFlexible' => ($schedule->is_flexible == 1) ? 'Yes' : 'No',
                    'IsRepeat' => ($schedule->is_repeat == 1) ? 'Yes' : 'No',
                    'PTO' => ! empty($req_approvals_pto) ? $req_approvals_pto->pto_hours_perday.' Hours PTO' : '',
                    'Leave' => ! empty($req_approvals_leave) ? 'On Leave' : '',
                    'ClockIn' => ! empty($user_checkin) ? $user_checkin : '',
                    'ClockOut' => ! empty($user_checkout) ? $user_checkout : '',
                    'CheckInClockHours' => ! empty($checkInTimeDifference) ? $checkInTimeDifference : '',
                    'IsPresent' => $is_present ? 'Yes' : 'No',
                    'IsLate' => $is_late ? 'Yes' : 'No',
                    'UserAttendenceApprovedStatus' => ($user_attendence_status) ? 'Approved' : 'Not Approved',

                ];
            }
            // return $formattedData;
            // return collect($formattedData);
        }

        // usort($formattedData, function ($a, $b) {
        //     return strtotime($a['ScheduleFrom']) - strtotime($b['ScheduleFrom']);
        // });
        return collect($formattedData);
    }

    public function headings(): array
    {
        return [
            'UserId',
            'FirstName',
            'MiddleName',
            'LastName',
            'LunchDuration',
            'ScheduleFrom',
            'ScheduleTo',
            'WorkDays',
            'IsAvailable',
            'DayName',
            'ClockHours',
            'IsFlexible',
            'IsRepeat',
            'PTO',
            'Leave',
            'ClockIn',
            'ClockOut',
            'CheckInClockHours',
            'IsPresent',
            'IsLate',
            'UserAttendenceApprovedStatus',
        ];
    }

    public function calculateTimeDifference($clockIn, $clockOut)
    {
        if (! is_null($clockIn) && ! is_null($clockOut)) {
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
            return $hours.'Hours '.$minutes.' Minutes';
        }

        return '0 Hours 0 Minutes';
    }

    private function getTimeFromDateTime($datetime)
    {
        // Parse the datetime string using Carbon
        $date = Carbon::parse($datetime);

        // Extract the time part
        $time = $date->format('H:i:s'); // This will give you the time in 'HH:MM:SS' format

        return $time;
    }

    public function compareDateTime($datetime1, $datetime2)
    {
        // Parse the datetime strings using Carbon
        $date1 = Carbon::parse($datetime1);
        $date2 = Carbon::parse($datetime2);
        $date2 = $this->getTimeFromDateTime($date2);

        // Check if the datetimes are equal
        // if ($date1->eq($date2)) {
        //     return 'equal';
        // }

        // Check if $datetime1 is greater than $datetime2
        if ($date2 != '00:00:00') {
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
}
