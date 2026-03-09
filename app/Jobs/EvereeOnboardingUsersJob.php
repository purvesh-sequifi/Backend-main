<?php

namespace App\Jobs;

use App\Events\EvereeOnboardingUserEvent;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvereeOnboardingUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $start_date;

    protected $end_date;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($start_date, $end_date)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // $paydata = Payroll::with('usersdata')->where(['pay_period_from' => $this->start_date, 'pay_period_to' => $this->end_date])->get();
        $paydata = Payroll::where('pay_period_from', $this->start_date)
            ->where('pay_period_to', $this->end_date)
            ->pluck('user_id');
        foreach ($paydata as $key => $uid) {
            $user_data = [];

            $user = User::where('id', $uid)->first();

            if ($user) {
                $user_data[] = $user->toArray();
            }
            if (! empty($user_data)) {
                event(new EvereeOnboardingUserEvent($user_data, $payroll = '0'));
            }
        }

    }
}
