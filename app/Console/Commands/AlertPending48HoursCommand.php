<?php

namespace App\Console\Commands;

use App\Models\PaymentAlertHistory;
use App\Models\PayrollHistory;
use App\Traits\EmailNotificationTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AlertPending48HoursCommand extends Command
{
    use EmailNotificationTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:alert-pending-48-hours';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a notification to the developer when a payment remains pending for over 48 hours.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! in_array(config('app.domain_name'), config('global_vars.DEMO_SERVERS'))) {
            $payments = [];
            $inCompleteOnboardedUsers = [];
            $threshold = Carbon::now()->subHours(48);
            $pendingPayments = PayrollHistory::with('usersdata')->whereNotIn('id', PaymentAlertHistory::where(['status' => 1])->pluck('payroll_id')->toArray())
                ->where(['pay_type' => 'Bank'])->where('net_pay', '>', 0)->whereIn('everee_payment_status', [1, 2])->where('created_at', '<=', $threshold)->get();
            foreach ($pendingPayments as $pendingPayment) {
                $payments[] = [
                    'id' => $pendingPayment->id,
                    'user_id' => $pendingPayment->user_id,
                    'user' => $pendingPayment->usersdata,
                    'worker_type' => $pendingPayment->worker_type,
                    'net_pay' => $pendingPayment->net_pay,
                    'pay_frequency_date' => $pendingPayment->pay_frequency_date,
                    'pay_period_from' => $pendingPayment->pay_period_from,
                    'pay_period_to' => $pendingPayment->pay_period_to,
                    'everee_webhook_json' => $pendingPayment->everee_webhook_json,
                    'created_at' => $pendingPayment->created_at,
                ];

                if ($pendingPayment?->usersdata?->onboardProcess != 1) {
                    $inCompleteOnboardedUsers[$pendingPayment->user_id]['email'] = $pendingPayment?->usersdata?->email;
                    $inCompleteOnboardedUsers[$pendingPayment->user_id]['user'] = $pendingPayment->usersdata;
                }

                PaymentAlertHistory::updateOrCreate(['payroll_id' => $pendingPayment->id], ['status' => 1]);
            }

            if ($inCompleteOnboardedUsers && count($inCompleteOnboardedUsers) != 0) {
                foreach ($inCompleteOnboardedUsers as $inCompleteOnboardedUser) {
                    $data = [
                        'email' => $inCompleteOnboardedUser['email'],
                        'subject' => 'Action Required - Onboarding Incomplete!',
                        'template' => view('mail.pending-payment-users-alerts', ['user' => $inCompleteOnboardedUser['user']]),
                    ];
                    $this->sendEmailNotification($data, true);
                }
            }
        }

        return Command::SUCCESS;
    }
}
