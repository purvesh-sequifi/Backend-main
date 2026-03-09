<?php

namespace App\Console\Commands;

use App\Models\SClearanceTurnScreeningRequestList;
use App\Traits\TurnAiTrait;
use Illuminate\Console\Command;

class SclearanceTurnRepoort extends Command
{
    use TurnAiTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sclearanceturnreport:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Sclerance missing reports';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $screening_requests = SClearanceTurnScreeningRequestList::whereNotNull('turn_id')->where('is_report_generated', 0)->get();

        foreach ($screening_requests as $screening_request) {
            $reportResponse = $this->getScreeningDetails($screening_request->turn_id);
            if (isset($reportResponse['partner_worker_status']) && ! empty($reportResponse['partner_worker_status'])) {
                $updateData = [];
                if (empty($screening_request->worker_id)) {
                    $updateData['worker_id'] = $reportResponse['worker_uuid'];
                }
                if ($reportResponse['partner_worker_status'] == 'approved' || $reportResponse['partner_worker_status'] == 'pending') {
                    $updateData['status'] = $reportResponse['partner_worker_status'];
                    $updateData['is_report_generated'] = 1;
                    $updateData['report_date'] = date('Y-m-d');
                } else {
                    $updateData['status'] = $reportResponse['partner_worker_status'];
                }

                SClearanceTurnScreeningRequestList::where(['id' => $screening_request->id])->update($updateData);
            }
        }
    }
}
