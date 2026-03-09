<?php

namespace App\Providers;

use App\Events\UserloginNotification;
use App\Listeners\HorizonEventListener;
use App\Listeners\JobPerformanceListener;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\UserloginNotificationListener;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommissionHistory;
use App\Models\UserDeductionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserRedlines;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWages;
use App\Models\UserWithheldHistory;
use App\Observers\SaleMastersObserver;
use App\Observers\UserCommissionHistoryObserver;
use App\Observers\UserDeductionHistoryObserver;
use App\Observers\UserObserver;
use App\Observers\UserOrganizationHistoryObserver;
use App\Observers\UserOverrideHistoryObserver;
use App\Observers\UserRedlinesObserver;
use App\Observers\UserSelfGenCommissionHistoryObserver;
use App\Observers\UserTransferHistoryObserver;
use App\Observers\UserUpfrontHistoryObserver;
use App\Observers\UserWagesObserver;
use App\Observers\UserWithheldHistoryObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The model observers for your application.
     *
     * @var array
     */
    protected $observers = [
        SalesMaster::class => [SaleMastersObserver::class],
        // NOTE: User::class observer is registered in AppServiceProvider (line 126, 338)
        // Employment Compensation Audit History Observers
        UserCommissionHistory::class => [UserCommissionHistoryObserver::class],
        UserRedlines::class => [UserRedlinesObserver::class],
        UserUpfrontHistory::class => [UserUpfrontHistoryObserver::class],
        UserWithheldHistory::class => [UserWithheldHistoryObserver::class],
        UserSelfGenCommmissionHistory::class => [UserSelfGenCommissionHistoryObserver::class],
        UserOverrideHistory::class => [UserOverrideHistoryObserver::class],
        UserOrganizationHistory::class => [UserOrganizationHistoryObserver::class],
        UserTransferHistory::class => [UserTransferHistoryObserver::class],
        UserWages::class => [UserWagesObserver::class],
        UserDeductionHistory::class => [UserDeductionHistoryObserver::class],
    ];

    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        UserloginNotification::class => [
            UserloginNotificationListener::class,
        ],
        \App\Events\EvereeOnboardingUserEvent::class => [
            \App\Listners\EvereeOnboardingUserListener::class,
        ],
        \App\Events\PaymentReceivedFromEveree::class => [
            \App\Listeners\ProcessPaymentFromEveree::class,
        ],

        \App\Events\PaymentReturnFromEveree::class => [
            \App\Listeners\ProcessPaymentReturnFromEveree::class,
        ],
        Login::class => [
            LogSuccessfulLogin::class,
        ],
        JobProcessing::class => [
            HorizonEventListener::class.'@handleProcessing',
            JobPerformanceListener::class.'@handleJobProcessing',
        ],
        JobProcessed::class => [
            HorizonEventListener::class.'@handleProcessed',
            JobPerformanceListener::class.'@handleJobProcessed',
        ],
        JobFailed::class => [
            HorizonEventListener::class.'@handleFailed',
            JobPerformanceListener::class.'@handleJobFailed',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
