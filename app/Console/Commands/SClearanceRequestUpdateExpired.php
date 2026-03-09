<?php

namespace App\Console\Commands;

use App\Models\SClearanceScreeningRequestList;
use App\Traits\SClearanceTrait;
use Illuminate\Console\Command;

class SClearanceRequestUpdateExpired extends Command
{
    use SClearanceTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SClearanceRequest:updateExpired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to update screeening request expired for incomplete requests';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /* Applicant expired status update */
        $screening_requests = SClearanceScreeningRequestList::whereNotNull('screening_request_applicant_id')
            ->whereNotNull('date_sent')
            ->whereNot('status', 'Application Expired')
            ->whereNull('report_date')
            ->get();

        if (! empty($screening_requests)) {
            foreach ($screening_requests as $screening_request) {
                // check for expired application
                $appliicantReportResponse = $this->getScreeningRequestApplicant($screening_request->screening_request_applicant_id);
                if (isset($appliicantReportResponse['applicantStatus']) && $appliicantReportResponse['applicantStatus'] == 'ScreeningRequestExpired') {
                    SClearanceScreeningRequestList::where(['id' => $screening_request->id])->update([
                        'status' => 'Application Expired',
                    ]);
                } else { // if expired status not coming from transunion but 30 days exceeds
                    $dateSent = strtotime($screening_request->date_sent);
                    $currentDate = strtotime(date('Y-m-d'));
                    $diffInSeconds = $currentDate - $dateSent; // seconds
                    $daysDifference = round($diffInSeconds / (60 * 60 * 24)); // days

                    if ($daysDifference >= 30) {
                        SClearanceScreeningRequestList::where(['id' => $screening_request->id])->update([
                            'status' => 'Application Expired',
                        ]);
                    }
                }
            }
        }

        /* Report expired status update */
        $screening_requests = SClearanceScreeningRequestList::whereNotNull('screening_request_applicant_id')
            ->whereNotNull('report_date')
            ->whereNotNull('report_expiry_date')
            ->whereNot('status', 'Report Expired')
            ->get();

        if (! empty($screening_requests)) {
            foreach ($screening_requests as $screening_request) {
                $currentDate = date('Y-m-d');
                if ($currentDate >= $screening_request->report_expiry_date) {
                    SClearanceScreeningRequestList::where(['id' => $screening_request->id])->update([
                        'status' => 'Report Expired',
                    ]);
                }
            }
        }
    }
}
