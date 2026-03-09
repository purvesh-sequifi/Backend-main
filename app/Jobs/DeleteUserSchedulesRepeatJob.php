<?php

namespace App\Jobs;

use App\Events\sendEventToPusher;
use App\Models\ApprovalsAndRequest;
use App\Models\CompanyProfile;
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

class DeleteUserSchedulesRepeatJob implements ShouldQueue
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
                // dd('ok');
                $schedule_id = $findUserSchedule->id;
                // dd($schedule_id);
                // Get current week's start and end dates
                $dateData = $this->getCurrentWeekDate();
                $currentWeekStart = $dateData['currentWeekStart'];
                $currentWeekEnd = $dateData['currentWeekEnd'];
                $profileData = CompanyProfile::where('id', 1)->first();
                // dd($profileData->time_zone);
                $timezone_arr = explode(' ', $profileData->time_zone);
                // dd($timezone_arr);
                $input = $timezone_arr[0];
                $timezone_val = preg_match('/\d{2}:\d{2}/', $input, $matches);
                $timePart = $matches[0];
                $timePartVal = explode(':', $timePart);
                $timePartHour = $timePartVal[0];
                $timePartMinute = $timePartVal[1];
                $timezone = timezone_name_from_abbr('', -($timePartHour) * 3600, 0);
                // dd($timezone);
                $checkFlag = false;
                // $currentWeekStart = Carbon::parse('2024-10-06',$timezone);
                // $currentWeekEnd = Carbon::parse('2024-10-12',$timezone);
                // $currentDate = Carbon::parse('2024-10-08');
                $currentDate = Carbon::now($timezone)->startOfDay();
                // dd($currentWeekStart, $currentWeekEnd, $currentDate);
                $startDate = $currentDate->addDay()->toDateString();
                // dd($startDate);
                if ($startDate >= $currentWeekStart && $startDate <= $currentWeekEnd) {
                    $checkFlag = true;
                }
                if ($checkFlag) {
                    $newWeekStart = Carbon::parse($startDate, $timezone);
                } else {
                    $newWeekStart = $currentDate;
                }
                // dd($currentWeekStart, $currentWeekEnd, $newWeekStart);

                if (! empty($newWeekStart) && ! empty($currentWeekEnd)) {
                    // dd($newWeekStart, $currentWeekEnd);
                    while ($newWeekStart->lte($currentWeekEnd)) {
                        Log::info($newWeekStart);
                        $scheduleFrom = $newWeekStart->copy()->setTime(0, 0, 0);
                        $scheduleTo = $newWeekStart->copy()->setTime(0, 0, 0);
                        $scheduleDetail = UserScheduleDetail::where('schedule_id', $schedule_id)
                            ->whereDate('schedule_from', $scheduleFrom->toDateString()) // Check for the date
                            ->first();
                        // dd($scheduleDetail, $scheduleFrom, $scheduleTo);
                        if ($scheduleDetail) {
                            $scheduleDetail->update([
                                'schedule_from' => $scheduleFrom,
                                'schedule_to' => $scheduleTo,
                                'lunch_duration' => 'None',
                                'updated_at' => Carbon::now(),
                            ]);
                            Log::info(['scheduleDetail' => $scheduleDetail]);
                        }
                        $newWeekStart->addDay();
                    }
                }
                // Loop over the next 12 weeks (3 months)
                for ($i = 1; $i <= 12; $i++) {
                    // Get future week dates
                    $nextWeek = $this->getFutureWeekStartAndEndDates($i);
                    $nextWeekStartDate = $nextWeek['next_week_start_date'];
                    $nextWeekEndDate = $nextWeek['next_week_end_date'];
                    // dd($nextWeek);
                    // Fetch schedules for this week in the next 3 months
                    $schedulesToDelete = UserScheduleDetail::where('schedule_id', $schedule_id)
                        ->whereBetween(DB::raw('DATE(schedule_from)'), [$nextWeekStartDate, $nextWeekEndDate])
                        ->get();
                    // dd($schedulesToDelete);
                    // Check if there are schedules to delete
                    if (! $schedulesToDelete->isEmpty()) {
                        // Delete schedules except for current week
                        foreach ($schedulesToDelete as $schedule) {
                            $schedule->delete();
                        }
                    }
                    // get any PTO and Leave request between nextWeekStartDate and nextWeekEndDate
                    // $requestsToBeDeleted = ApprovalsAndRequest::where(function($query) use ($nextWeekStartDate, $nextWeekEndDate) {
                    //     $query->whereBetween('start_date', [$nextWeekStartDate, $nextWeekEndDate])
                    //           ->orWhereBetween('end_date', [$nextWeekStartDate, $nextWeekEndDate]);
                    // })
                    //     ->whereIn('adjustment_type_id', [7, 8])
                    //     ->get();
                    // dd($requestsToBeDeleted);
                    // if (!$requestsToBeDeleted->isEmpty()) {

                    //     ApprovalsAndRequest::where(function($query) use ($nextWeekStartDate, $nextWeekEndDate) {
                    //         $query->whereBetween('start_date', [$nextWeekStartDate, $nextWeekEndDate])
                    //               ->orWhereBetween('end_date', [$nextWeekStartDate, $nextWeekEndDate]);
                    //     })
                    //     ->whereIn('adjustment_type_id', [7, 8])
                    //     ->delete();
                    // }
                }

                // Optionally, send event to Pusher
                // Log the pusher variavles for verify
                Log::info(['PUSHER_APP_KEY' => config('broadcasting.connections.pusher.key'), 'PUSHER_APP_SECRET' => config('broadcasting.connections.pusher.secret'), 'PUSHER_APP_ID' => config('broadcasting.connections.pusher.app_id'), 'PUSHER_APP_CLUSTER' => config('broadcasting.connections.pusher.options.cluster')]);
                $pusherMsg = 'Next 3 months schedules deleted successfully (excluding the current week)';
                $pusherEvent = '3-months-delete-schedules';
                $domainName = config('app.domain_name');
                $dataForPusherEvent = $this->dataForPusher ?? [];
                event(new sendEventToPusher($domainName, $pusherEvent, $pusherMsg, $dataForPusherEvent));
            }
        } catch (\Exception $e) {
            // Log the error
            \Log::error($e->getMessage());
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
