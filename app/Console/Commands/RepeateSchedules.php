<?php

namespace App\Console\Commands;

use App\Models\TestData;
use App\Models\UserSchedule;
use App\Models\UserScheduleDetail;
use App\Traits\EmailNotificationTrait;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;

class RepeateSchedules extends Command
{
    use EmailNotificationTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repeat:schedules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Schedules create for is_repeat schedules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // return Command::SUCCESS;
        /*$repeate_users = UserSchedule::where('is_repeat', 1)->get();
        foreach ($repeate_users as $repeate) {
            $nextWeek = $this->getNextWeekStartAndEndDates();
            $nextWeekStartDate = $nextWeek['next_week_start_date'];
            $nextWeekEndDate = $nextWeek['next_week_end_date'];
            // dd($nextWeekStartDate);
            $existingSchedules = UserScheduleDetail::where('schedule_id', $repeate->id)
                ->whereBetween('schedule_from', [$nextWeekStartDate, $nextWeekEndDate])
                ->exists();
            // dd($existingSchedules);
            if (!$existingSchedules) {
                $pre_schedules = UserScheduleDetail::where('schedule_id', $repeate->id)
                ->orderBy('id', 'DESC')->limit(7)
                ->get();
                // dd($pre_schedules);
                $scheduleArray = [];
                if (!empty($pre_schedules) && count($pre_schedules) > 0) {

                    foreach ($pre_schedules as $schedule) {
                        //echo ($schedule->schedule_from);
                        // Calculate the new dates for the next week
                        $newScheduleFrom = Carbon::parse($schedule->schedule_from)
                            ->addWeek()
                            ->toDateTimeString();
                        $newScheduleTo = Carbon::parse($schedule->schedule_to)
                            ->addWeek()
                            ->toDateTimeString();
                        //dd($schedule->schedule_from,$newScheduleFrom, $newScheduleTo);
                        $scheduleArray[] = [
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
                            "created_at" => Carbon::now()->toDateString(),
                            "updated_at" => Carbon::now()->toDateString()
                        ];
                    }
                    //dd($scheduleArray);
                    // DB::beginTransaction();
                    // foreach($scheduleArray as $schedule_row){
                    //     //dd($schedule_row['schedule_id']);
                    //     $checkUserScheduleDetailsData = UserScheduleDetail::where('schedule_id', $schedule_row['schedule_id'])
                    //             ->where('office_id', $schedule_row['office_id'])
                    //             ->whereDate('schedule_from', $schedule_row['schedule_from'])
                    //             ->where('work_days', $schedule_row['work_days'])
                    //             ->exists();
                    //     if ($checkUserScheduleDetailsData) {
                    //         DB::rollBack();
                    //         return response()->json(['message' => 'A schedule already exists, no data was inserted.'], 400);
                    //     }
                    // }
                     //echo "<pre>"; print_r($scheduleArray); die;
                     UserScheduleDetail::insert($scheduleArray);
                }
            }


        }*/
        try {
            $message = 'The cron for creating schedules ran successfully at '.Carbon::now();
            $testdata = new TestData;
            $testdata->first_name = 'Test';
            $testdata->last_name = 'CRON';
            $testdata->email = 'CRON@gmail.com';
            $testdata->save();
            // return;
            $repeate_users = UserSchedule::where('is_repeat', 1)->get();
            // new function code for 3 months schedules
            foreach ($repeate_users as $repeate) {
                // Calculate schedules for the next 3 months (12 weeks)
                $scheduleArray = [];
                $dateData = $this->currentWeekDate();
                $currentWeekStart = $dateData['currentWeekStart'];
                $currentWeekEnd = $dateData['currentWeekEnd'];
                // dd($dateData, $currentWeekStart, $currentWeekEnd);
                for ($i = 1; $i <= 12; $i++) {
                    $nextWeek = $this->getFutureWeekStartAndEndDates($i);
                    $nextWeekStartDate = $nextWeek['next_week_start_date'];
                    $nextWeekEndDate = $nextWeek['next_week_end_date'];
                    // echo $nextWeekStartDate,' ---- '. $nextWeekEndDate;echo "\n";
                    $existingSchedules = UserScheduleDetail::where('schedule_id', $repeate->id)
                        ->whereBetween('schedule_from', [$nextWeekStartDate, $nextWeekEndDate])
                        ->exists();
                    // dd($existingSchedules,$nextWeekStartDate, $nextWeekEndDate);

                    if (! $existingSchedules) {
                        $pre_schedules = UserScheduleDetail::where('schedule_id', $repeate->id)
                            ->whereBetween('schedule_from', [$currentWeekStart, $currentWeekEnd])
                            // ->orderBy('id', 'DESC')->limit(7)
                            ->get();
                        // dd($pre_schedules);
                        // $scheduleArray = [];
                        if (! empty($pre_schedules) && count($pre_schedules) > 0) {
                            foreach ($pre_schedules as $schedule) {
                                $newScheduleFrom = Carbon::parse($schedule->schedule_from)
                                    ->addWeeks($i)
                                    ->toDateTimeString();
                                $newScheduleTo = Carbon::parse($schedule->schedule_to)
                                    ->addWeeks($i)
                                    ->toDateTimeString();

                                $scheduleArray[] = [
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
                                    'created_at' => Carbon::now()->toDateString(),
                                    'updated_at' => Carbon::now()->toDateString(),
                                ];
                            }
                            // dd($scheduleArray);
                            // UserScheduleDetail::insert($scheduleArray);
                        }
                        $existingSchedules = UserScheduleDetail::where('schedule_id', $repeate->id)
                            ->whereBetween('schedule_from', [$nextWeekStartDate, $nextWeekEndDate])
                            ->exists();
                        if (! $existingSchedules) {
                            // dd(1);
                            // UserScheduleDetail::insert($scheduleArray);
                        }

                    }
                }
                // dd($scheduleArray);
                usort($scheduleArray, function ($a, $b) {
                    return strtotime($a['schedule_from']) - strtotime($b['schedule_from']);
                });
                // dd($scheduleArray);
                UserScheduleDetail::insert($scheduleArray);
            }
        } catch (\Exception $e) {
            // In case of any exception, send error email
            $message = 'Error occurred while running the cron: '.$e->getMessage();
            \Log::error($message);

            // Optionally, rethrow the exception
            throw $e;
        }

    }

    private function getUserCurrentWeekStartAndEndDates()
    {
        // Get the current date
        $currentDate = Carbon::now();

        // Calculate the start of the current week (Monday)
        $currentWeekStartDate = $currentDate->copy()->startOfWeek(Carbon::SUNDAY);

        // Calculate the end of the current week (Sunday)
        $currentWeekEndDate = $currentWeekStartDate->copy()->endOfWeek(Carbon::SATURDAY);

        return [
            'current_week_start_date' => $currentWeekStartDate->toDateString(),
            'current_week_end_date' => $currentWeekEndDate->toDateString(),
        ];
    }

    private function getNextWeekStartAndEndDates()
    {
        // Get the current date
        $currentDate = Carbon::now();

        // Calculate the start of the next week (Sunday)
        $nextWeekStartDate = $currentDate->copy()->addWeek()->startOfWeek(Carbon::SUNDAY);

        // Calculate the end of the next week (Saturday)
        $nextWeekEndDate = $nextWeekStartDate->copy()->endOfWeek(Carbon::SATURDAY);

        return [
            'next_week_start_date' => $nextWeekStartDate->toDateString(),
            'next_week_end_date' => $nextWeekEndDate->toDateString(),
        ];
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
}
