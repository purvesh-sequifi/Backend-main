<?php

namespace App\Jobs;

use App\Models\AdditionalInfoForEmployeeToGetStarted;
use App\Models\Integration;
use App\Services\BigQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AddUpdateAdditionalInfoForEmployeeGetStartedOnBigQueryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $additionalInfoForEmployeeToGetStartedArray;

    public $tries = 3; // Number of retry attempts

    public $timeout = 120; // Timeout in seconds

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // No BigQueryService initialization here
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $bigQueryService = new BigQueryService;
        AdditionalInfoForEmployeeToGetStarted::orderBy('id', 'asc')->chunk(100, function ($additionalInfoForEmployeeToGetStarteds) use ($bigQueryService) {
            foreach ($additionalInfoForEmployeeToGetStarteds as $additionalInfoForEmployeeToGetStarted) {
                // if($additionalInfoForEmployeeToGetStarted->id==2){
                $this->addOrUpdateAdditionalInfoForEmployeeToGetStartedOnBigQuery($additionalInfoForEmployeeToGetStarted, $bigQueryService);
                // }
            }
        });

        Log::info('AddUpdateAdditionalInfoForEmployeeToGetStartedOnBigQueryJob completed successfully.');
    }

    /**
     * Add or update additionalInfoForEmployeeToGetStarted record on BigQuery.
     */
    private function addOrUpdateAdditionalInfoForEmployeeToGetStartedOnBigQuery(AdditionalInfoForEmployeeToGetStarted $additionalInfoForEmployeeToGetStarted, BigQueryService $bigQueryService)
    {
        $this->additionalInfoForEmployeeToGetStartedArray = $additionalInfoForEmployeeToGetStarted->toArray();

        $datasetId = 'sequifi';
        $tableId = 'additional_info_for_employee_to_get_started';
        $additionalInfoForEmployeeToGetStartedIdField = 'id';
        $additionalInfoForEmployeeToGetStartedId = $additionalInfoForEmployeeToGetStarted->id;
        $dataType = 'INT64';
        // $this->addAdditionalInfoForEmployeeToGetStartedOnBigQuery($bigQueryService);
        // Check if the additionalInfoForEmployeeToGetStarted exists in BigQuery
        $additionalInfoForEmployeeToGetStartedExists = $bigQueryService->checkRecordExists($datasetId, $tableId, $additionalInfoForEmployeeToGetStartedIdField, $additionalInfoForEmployeeToGetStartedId, $dataType);
        Log::info("additionalInfoForEmployeeToGetStartedExists {$additionalInfoForEmployeeToGetStartedExists} in BigQuery.");
        if ($additionalInfoForEmployeeToGetStartedExists) {
            $this->updateAdditionalInfoForEmployeeToGetStartedOnBigQuery($bigQueryService);
            Log::info("Updated additionalInfoForEmployeeToGetStarted ID {$additionalInfoForEmployeeToGetStarted->id} in BigQuery.");
        } else {
            $this->addAdditionalInfoForEmployeeToGetStartedOnBigQuery($bigQueryService);
            Log::info("Added additionalInfoForEmployeeToGetStarted ID {$additionalInfoForEmployeeToGetStarted->id} to BigQuery.");
        }
        // $this->addAdditionalInfoForEmployeeToGetStartedOnBigQuery($bigQueryService);
        // $this->updateAdditionalInfoForEmployeeToGetStartedOnBigQuery($bigQueryService);
        Log::info("Processing additionalInfoForEmployeeToGetStarted ID {$additionalInfoForEmployeeToGetStarted->id}: {$additionalInfoForEmployeeToGetStarted->name}");
    }

    private function addAdditionalInfoForEmployeeToGetStartedOnBigQuery(BigQueryService $bigQueryService)
    {
        $integration = Integration::where('status', 1)->first();
        if (! empty($integration)) {
            $data = ['data' => $this->additionalInfoForEmployeeToGetStartedArray];
            $bigQueryResponse = $bigQueryService->insertData('sequifi', 'additional_info_for_employee_to_get_started', $data);
        }
    }

    private function updateAdditionalInfoForEmployeeToGetStartedOnBigQuery(BigQueryService $bigQueryService)
    {
        $integration = Integration::where('status', 1)->first();
        if (! empty($integration)) {
            $data = $this->additionalInfoForEmployeeToGetStartedArray;
            $condition = "id = CAST({$this->additionalInfoForEmployeeToGetStartedArray['id']} AS INT64)"; // WHERE condition
            Log::info("Condition for update: $condition");
            $updates = $this->additionalInfoForEmployeeToGetStartedArray;
            $ColumnArray = ['mobile_no', 'worker_type', 'pay_rate', 'pto_hours', 'zip_code', 'commission', 'redline', 'upfront_pay_amount', 'direct_overrides_amount', 'indirect_overrides_amount', 'office_overrides_amount'];
            $bigQueryResponse = $bigQueryService->updateRecords('sequifi', 'additional_info_for_employee_to_get_started', $condition, $updates, $ColumnArray);
        }
    }
}
