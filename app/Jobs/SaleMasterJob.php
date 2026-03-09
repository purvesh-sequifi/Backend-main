<?php

namespace App\Jobs;

use App\Events\sendEventToPusher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaleMasterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200; // 20 minutes

    public $user;

    public $isPest;

    public $dataForPusher;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $isPest, $dataForPusher)
    {
        $this->onQueue('sales-process');
        $this->user = $user;
        $this->isPest = $isPest;
        $this->dataForPusher = $dataForPusher;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->isPest) {
            pest_excel_insert_update_sale_master($this->user);
        } else {
            excel_insert_update_sale_master($this->user);
        }

        /* Send event to pusher */
        $pusherMsg = 'Sales imported successfully';
        $pusherEvent = 'sale-import-excel';
        $domainName = config('app.domain_name');
        $dataForPusherEvent = [];
        if (! empty($this->dataForPusher)) {
            $dataForPusherEvent = $this->dataForPusher;
        }

        // event(new sendEventToPusher($domainName, $pusherEvent, $pusherMsg, $dataForPusherEvent));
        /* Send event to pusher */
    }
}
