<?php

namespace App\Jobs;

use App\Events\sendEventToPusher;
use App\Models\UserSchedule;
use App\Models\UserScheduleDetail;
use Carbon\Carbon;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateUserSchedulesRepeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    protected $user_id;

    protected $is_repeat;

    public $dataForPusher;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user_id, $is_repeat, $dataForPusher = [])
    {
        $this->user_id = $user_id;
        $this->is_repeat = $is_repeat;
        $this->dataForPusher = $dataForPusher;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $findUserSchedule = UserSchedule::where('user_id', $this->user_id)->first();
            if (! empty($findUserSchedule)) {
                $schedule_id = $findUserSchedule->id;

                if ($this->is_repeat == 1) {
                    $dateData = $this->getCurrentWeekDate();
                    $currentWeekStart = $dateData['currentWeekStart'];
                    $currentWeekEnd = $dateData['currentWeekEnd'];
                    // dd($currentWeekStart, $currentWeekEnd);
                    for ($i = 1; $i <= 12; $i++) {
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
                        if (! $existingSchedules->isEmpty()) {
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
                                    $newScheduleFrom = Carbon::parse($existingSchedule['schedule_from'])->format('Y-m-d').' '.$newTimeFrom;
                                    $newScheduleTo = Carbon::parse($existingSchedule['schedule_to'])->format('Y-m-d').' '.$newTimeTo;

                                    // Update the existing schedule with the new time while keeping the date intact
                                    $updatedarray = [
                                        'schedule_from' => $newScheduleFrom,
                                        'schedule_to' => $newScheduleTo,
                                        'lunch_duration' => $lunch_duration,
                                        'is_flexible' => $is_flexible,
                                        'updated_at' => Carbon::now(),
                                    ];
                                    // dd($updatedarray);
                                    $updateSchedule = $existingSchedule->update([
                                        'schedule_from' => $newScheduleFrom,
                                        'schedule_to' => $newScheduleTo,
                                        'lunch_duration' => $lunch_duration,
                                        'is_flexible' => $is_flexible,
                                        'updated_at' => Carbon::now(),
                                    ]);
                                }
                                $res = true;
                            }

                        } else {
                            $pre_schedules = UserScheduleDetail::where('schedule_id', $schedule_id)
                                ->whereBetween(DB::raw('DATE(schedule_from)'), [$currentWeekStart, $currentWeekEnd])
                                ->get();
                            if (! empty($pre_schedules) && count($pre_schedules) > 0) {
                                foreach ($pre_schedules as $schedule) {
                                    // return $schedule;
                                    $newScheduleFrom = Carbon::parse($schedule->schedule_from)
                                        ->addWeeks($i)
                                        ->toDateTimeString();
                                    $newScheduleTo = Carbon::parse($schedule->schedule_to)
                                        ->addWeeks($i)
                                        ->toDateTimeString();
                                    // dd($newScheduleFrom, $newScheduleTo);
                                    $scheduleCreatedArray[] = [
                                        'schedule_id' => $schedule->schedule_id,
                                        'office_id' => $schedule->office_id,
                                        'schedule_from' => $newScheduleFrom,
                                        'schedule_to' => $newScheduleTo,
                                        'lunch_duration' => $schedule->lunch_duration,
                                        'work_days' => $schedule->work_days,
                                        'repeated_batch' => $schedule->repeated_batch,
                                        'updated_by' => $schedule->updated_by,
                                        'updated_type' => $schedule->updated_type,
                                        'user_attendance_id' => null,
                                        'attendance_status' => 0,
                                        'is_flexible' => $schedule->is_flexible,
                                        'created_at' => Carbon::now(),
                                        'updated_at' => Carbon::now(),
                                    ];
                                }
                                // dd($scheduleCreatedArray);
                            }

                        }

                    }
                    if (isset($scheduleCreatedArray) && ! empty($scheduleCreatedArray)) {
                        // dd(33, count($scheduleCreatedArray));
                        // usort($scheduleCreatedArray, function ($a, $b) {
                        //     return strtotime($a['schedule_from']) - strtotime($b['schedule_from']);
                        // });
                        $createdSchedules = UserScheduleDetail::insert($scheduleCreatedArray);
                        if ($createdSchedules) {
                            $res = true;
                        }
                    }
                    // if($res){
                    //     return true;
                    // }else{
                    //     return false;
                    // }
                    /* Send event to pusher */
                    $pusherMsg = '3 months schedules created / updated successfully';
                    $pusherEvent = '3-months-repeat-schedules';
                    $domainName = config('app.domain_name');
                    $dataForPusherEvent = [];
                    if (! empty($this->dataForPusher)) {
                        $dataForPusherEvent = $this->dataForPusher;
                    }
                    event(new sendEventToPusher($domainName, $pusherEvent, $pusherMsg, $dataForPusherEvent));
                    /* Send event to pusher */

                }

            }
        } catch (\Exception $e) {
            // Log the error
            \Log::error($message);
            throw $e;
        }
    }

    private function getCurrentWeekDate()
    {
        $currentDate = Carbon::now();
        $currentWeekStart = $currentDate->copy()->startOfWeek(Carbon::SUNDAY);
        $currentWeekEnd = $currentDate->copy()->endOfWeek(Carbon::SATURDAY);

        return [
            'currentWeekStart' => $currentWeekStart,
            'currentWeekEnd' => $currentWeekEnd,
        ];
    }

    private function getFutureWeekStartAndEndDates($weekOffset)
    {
        // $nextWeekStartDate = Carbon::now()->startOfWeek()->addWeeks($weekOffset)->toDateString();
        // $nextWeekEndDate = Carbon::now()->endOfWeek()->addWeeks($weekOffset)->toDateString();

        // return [
        //     'next_week_start_date' => $nextWeekStartDate,
        //     'next_week_end_date' => $nextWeekEndDate,
        // ];
        $currentDate = Carbon::now();
        // Calculate the start of the future week
        $futureWeekStartDate = $currentDate->copy()->addWeeks($weekOffset)->startOfWeek(Carbon::SUNDAY);

        // Calculate the end of the future week
        $futureWeekEndDate = $futureWeekStartDate->copy()->endOfWeek(Carbon::SATURDAY);

        return [
            'next_week_start_date' => $futureWeekStartDate->toDateString(),
            'next_week_end_date' => $futureWeekEndDate->toDateString(),
        ];
    }
}
