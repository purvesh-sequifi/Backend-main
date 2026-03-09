<?php

namespace App\Jobs;

use App\Events\sendEventToPusher;
use App\Traits\EmailNotificationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessRecalculatesOpenSales implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes

    protected $pids;

    protected $dataForPusher;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($pids, $dataForPusher)
    {
        $this->onQueue('default');
        $this->pids = $pids;
        $this->dataForPusher = $dataForPusher;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            foreach ($this->pids as $pid) {
                $namespace = app()->getNamespace();
                $SaleRecalculateController = app()->make($namespace.\Http\Controllers\API\SaleRecalculateController::class);

                $request = new \Illuminate\Http\Request;
                $request->merge(['pid' => $pid]);

                $SaleRecalculateController->recalculateSaleData($request);
            }

            /* Send event to pusher */
            $pusherMsg = 'Sale recalculated successfully';
            $pusherEvent = 'recalculate-sale';
            $domainName = config('app.domain_name');
            $dataForPusherEvent = [];
            if (! empty($this->dataForPusher)) {
                $dataForPusherEvent = $this->dataForPusher;
            }
            // event(new sendEventToPusher($domainName, $pusherEvent, $pusherMsg, $dataForPusherEvent));
        } catch (Throwable $e) {
        }
    }
}
