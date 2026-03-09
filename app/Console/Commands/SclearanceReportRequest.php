<?php

namespace App\Console\Commands;

use App\Models\SClearanceScreeningRequestList;
use App\Traits\SClearanceTrait;
use Illuminate\Console\Command;

class SclearanceReportRequest extends Command
{
    use SClearanceTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sclearancerequestreport:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Request Sclerance pending reports';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $screening_requests = SClearanceScreeningRequestList::whereNotNull('screening_request_applicant_id')->where('is_report_generated', 0)->get();

        foreach ($screening_requests as $screening_request) {
            $ApplicantReportsResponse = $this->postApplicantReport(['screeningRequestApplicantId' => $screening_request->screening_request_applicant_id, 'applicantId' => $screening_request->applicant_id]);
            if (isset($ApplicantReportsResponse['applicantStatus'])) {
                $reportResponse = $this->getScreeningReports($screening_request->screening_request_applicant_id);
                if (isset($reportResponse['reportResponseModelDetails']) && isset($reportResponse['reportResponseModelDetails'][0]['reportData'])) {
                    // Create pdf and Upload to S3
                    // $this->createPDFAndSavetoS3($reportResponse['reportResponseModelDetails'][0]['reportData'], $data->ScreeningRequestApplicantId);

                    SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $screening_request->screening_request_applicant_id])->update([
                        'status' => 'Approval Pending',  // 6
                        'is_report_generated' => 1,
                        'report_date' => date('Y-m-d'),
                        'report_expiry_date' => date('Y-m-d', strtotime('+'.$reportResponse['reportsExpireNumberOfDays'].' days')),
                    ]);
                }

            }
        }
    }
}
