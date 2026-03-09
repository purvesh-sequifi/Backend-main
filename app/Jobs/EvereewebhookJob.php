<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use App\Events\PaymentReturnFromEveree;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\PaymentReceivedFromEveree;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class EvereewebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $tries = 3;
    public $timeout = 1200;
    public $data, $eventType;

    public function __construct($data, $eventType)
    {
        $this->data = $data;
        $this->eventType = $eventType;
        $this->onQueue('everee');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->data;
        $eventType = $this->eventType;
        if ($eventType == true) {
            event(new PaymentReceivedFromEveree($data));
        } else if ($eventType == false) {
            event(new PaymentReturnFromEveree($data));
        }
    }
}
