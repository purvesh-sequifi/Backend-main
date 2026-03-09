<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\Lead;
use App\Services\BigQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AddUpdateLeadsOnBigQueryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $leadArray;

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
        Lead::chunk(100, function ($leads) use ($bigQueryService) {
            foreach ($leads as $lead) {
                // if($lead->id==2){
                $this->addOrUpdateLeadOnBigQuery($lead, $bigQueryService);
                // }
            }
        });

        Log::info('AddUpdateLeadOnBigQueryJob completed successfully.');
    }

    /**
     * Add or update lead record on BigQuery.
     */
    private function addOrUpdateLeadOnBigQuery(Lead $lead, BigQueryService $bigQueryService)
    {
        $this->leadArray = $lead->toArray();

        $datasetId = 'sequifi';
        $tableId = 'leads';
        $leadIdField = 'id';
        $leadId = $lead->id;
        $dataType = 'STRING';
        // $this->addLeadOnBigQuery($bigQueryService);
        // Check if the lead exists in BigQuery
        $leadExists = $bigQueryService->checkRecordExists($datasetId, $tableId, $leadIdField, $leadId, $dataType);
        if ($leadExists) {
            $this->updateLeadOnBigQuery($bigQueryService);
            Log::info("Updated lead ID {$lead->id} in BigQuery.");
        } else {
            $this->addLeadOnBigQuery($bigQueryService);
            Log::info("Added lead ID {$lead->id} to BigQuery.");
        }
        // $this->addLeadOnBigQuery($bigQueryService);
        // $this->updateLeadOnBigQuery($bigQueryService);
        Log::info("Processing lead ID {$lead->id}: {$lead->name}");
    }

    private function addLeadOnBigQuery(BigQueryService $bigQueryService)
    {
        $integration = Integration::where('status', 1)->first();
        if (! empty($integration)) {
            $data = ['data' => $this->leadArray];
            $bigQueryResponse = $bigQueryService->insertData('sequifi', 'leads', $data);
        }
    }

    private function updateLeadOnBigQuery(BigQueryService $bigQueryService)
    {
        $integration = Integration::where('status', 1)->first();
        if (! empty($integration)) {
            $data = $this->leadArray;
            $condition = "id = CAST({$this->leadArray['id']} AS STRING)"; // WHERE condition
            Log::info("Condition for update: $condition");
            $updates = $this->leadArray;
            $ColumnArray = ['id', 'mobile_no', 'worker_type', 'pipeline_status_id', 'pipeline_status_date'];
            $bigQueryResponse = $bigQueryService->updateRecords('sequifi', 'leads', $condition, $updates, $ColumnArray);
        }
    }
}
