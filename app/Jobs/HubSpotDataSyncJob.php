<?php

namespace App\Jobs;

use App\Core\Traits\HubspotTrait;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HubSpotDataSyncJob implements ShouldQueue
{
    use Dispatchable, HubspotTrait, InteractsWithQueue, Queueable , SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $token;

    public $data;

    public $timeout = 600; // 10 minutes

    public $tries = 3;

    public function __construct($token, $data)
    {
        $this->token = $token;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // $data = User::where('aveyo_hs_id',null)->orWhere('aveyo_hs_id',0)->where('is_super_admin','!=',1)->get();
        // $data = User::where('aveyo_hs_id',null)->orWhere('aveyo_hs_id',0)->where('is_super_admin','!=',1)->orWhere('aveyo_hs_id','!=',null)->get();
        $hubspotSaleDataCreate = $this->SyncHsSalesDataCreate($this->data, $this->token);
        // $hubspotSaleDataCreate = $this->SyncHsSalesDataCreate($data,$token);
    }
}
