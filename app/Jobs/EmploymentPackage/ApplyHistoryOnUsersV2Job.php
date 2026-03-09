<?php

namespace App\Jobs\EmploymentPackage;

use App\Traits\EmailNotificationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class ApplyHistoryOnUsersV2Job implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [30, 60, 120];

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 1800; // 30 minutes

    protected $users;

    public $authUserId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($users, $authUserId)
    {
        $this->users = $users;
        $this->authUserId = $authUserId;
        $this->onQueue('sales-process');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Artisan::call('ApplyHistoryOnUsersV2:update', ['user_id' => $this->users, 'auth_user_id' => $this->authUserId]);
    }

    public function failed(\Throwable $e)
    {
        //
    }
}
