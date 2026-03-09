<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\NewSequiDocsDocument;
use App\Services\BigQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AddUpdateNewSequiDocsDocumentOnBigQueryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 300; // Increased timeout to 5 minutes

    public $maxExceptions = 2;

    public $backoff = [30, 60, 120]; // Delay between retries in seconds

    const DATASET_ID = 'sequifi';

    const TABLE_ID = 'new_sequi_docs_documents';

    const UPDATE_COLUMNS = [
        'mobile_no',
        'worker_type',
        'signature_request_id',
        'signature_request_document_id',
    ];

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // No BigQueryService initialization here
    }

    public function handle(): void
    {
        Log::info('Starting SequiDocs document sync with BigQuery');

        // Check if integration is active
        if (! $this->isIntegrationActive()) {
            Log::warning('Integration is not active. Job terminated.');

            return;
        }

        $bigQueryService = new BigQueryService;
        $processed = 0;
        $batchSize = 50; // Optimal batch size for most environments

        NewSequiDocsDocument::query()
            ->chunkById($batchSize, function ($documents) use ($bigQueryService, &$processed) {
                foreach ($documents as $document) {
                    try {
                        $this->syncDocument($document, $bigQueryService);
                        $processed++;
                    } catch (\Exception $e) {
                        Log::error("Failed to sync document ID {$document->id}: ".$e->getMessage());

                        continue;
                    }
                }

                // Memory management
                unset($documents);
                gc_collect_cycles();
            });

        Log::info("Completed SequiDocs document sync. Processed {$processed} documents.");
    }

    protected function isIntegrationActive(): bool
    {
        return Integration::where('status', 1)->exists();
    }

    protected function syncDocument(NewSequiDocsDocument $document, BigQueryService $service)
    {
        $documentData = $document->toArray();

        if ($this->documentExistsInBigQuery($service, $document->id)) {
            $this->updateDocument($service, $documentData);
        } else {
            $this->insertDocument($service, $documentData);
        }
    }

    protected function documentExistsInBigQuery(BigQueryService $service, int $documentId): bool
    {
        return $service->checkRecordExists(
            self::DATASET_ID,
            self::TABLE_ID,
            'id',
            $documentId,
            'INT64'
        );
    }

    protected function insertDocument(BigQueryService $service, array $documentData)
    {
        $service->insertData(
            self::DATASET_ID,
            self::TABLE_ID,
            ['data' => $documentData]
        );

        Log::debug("Inserted document ID {$documentData['id']} to BigQuery");
    }

    protected function updateDocument(BigQueryService $service, array $documentData)
    {
        $condition = "id = CAST({$documentData['id']} AS INT64)";

        $service->updateRecords(
            self::DATASET_ID,
            self::TABLE_ID,
            $condition,
            $documentData,
            self::UPDATE_COLUMNS
        );

        Log::debug("Updated document ID {$documentData['id']} in BigQuery");
    }

    public function failed(Throwable $exception)
    {
        Log::critical('SequiDocs BigQuery sync job failed: '.$exception->getMessage(), [
            'exception' => $exception,
            'job' => $this->job?->getJobId(),
        ]);

        // Optional: Send notification to admin
        // Notification::send(/* admin */, new JobFailedNotification($exception));
    }

    /**
     * Execute the job.
     */
    // public function handle()
    // {
    //     $bigQueryService = new BigQueryService();
    //     NewSequiDocsDocument::chunk(100, function ($newSequiDocsDocuments) use ($bigQueryService) {
    //         foreach ($newSequiDocsDocuments as $newSequiDocsDocument) {
    //             //if($newSequiDocsDocument->id==2){
    //                 $this->addOrUpdateNewSequiDocsDocumentOnBigQuery($newSequiDocsDocument, $bigQueryService);
    //             //}
    //         }
    //     });

    //     Log::info('AddUpdateNewSequiDocsDocumentOnBigQueryJob completed successfully.');
    // }

    // /**
    //  * Add or update newSequiDocsDocument record on BigQuery.
    //  *
    //  * @param NewSequiDocsDocument $newSequiDocsDocument
    //  * @param BigQueryService $bigQueryService
    //  */
    // private function addOrUpdateNewSequiDocsDocumentOnBigQuery(NewSequiDocsDocument $newSequiDocsDocument, BigQueryService $bigQueryService)
    // {
    //     $this->newSequiDocsDocumentArray = $newSequiDocsDocument->toArray();

    //     $datasetId = 'sequifi';
    //     $tableId = 'new_sequi_docs_documents';
    //     $newSequiDocsDocumentIdField = 'id';
    //     $newSequiDocsDocumentId = $newSequiDocsDocument->id;
    //     $dataType = 'INT64';
    //     //$this->addNewSequiDocsDocumentOnBigQuery($bigQueryService);
    //     // Check if the newSequiDocsDocument exists in BigQuery
    //     $newSequiDocsDocumentExists = $bigQueryService->checkRecordExists($datasetId, $tableId, $newSequiDocsDocumentIdField, $newSequiDocsDocumentId, $dataType);
    //     if ($newSequiDocsDocumentExists) {
    //         $this->updateNewSequiDocsDocumentOnBigQuery($bigQueryService);
    //         Log::info("Updated newSequiDocsDocument ID {$newSequiDocsDocument->id} in BigQuery.");
    //     } else {
    //         $this->addNewSequiDocsDocumentOnBigQuery($bigQueryService);
    //         Log::info("Added newSequiDocsDocument ID {$newSequiDocsDocument->id} to BigQuery.");
    //     }
    //     //$this->addNewSequiDocsDocumentOnBigQuery($bigQueryService);
    //     //$this->updateNewSequiDocsDocumentOnBigQuery($bigQueryService);
    //     Log::info("Processing newSequiDocsDocument ID {$newSequiDocsDocument->id}: {$newSequiDocsDocument->name}");
    // }

    // private function addNewSequiDocsDocumentOnBigQuery(BigQueryService $bigQueryService)
    // {
    //     $integration = Integration::where("status", 1)->first();
    //     if (!empty($integration)) {
    //         $data = ['data' => $this->newSequiDocsDocumentArray];
    //         $bigQueryResponse = $bigQueryService->insertData('sequifi', 'new_sequi_docs_documents', $data);
    //     }
    // }

    // private function updateNewSequiDocsDocumentOnBigQuery(BigQueryService $bigQueryService)
    // {
    //     $integration = Integration::where("status", 1)->first();
    //     if (!empty($integration)) {
    //         $data = $this->newSequiDocsDocumentArray;
    //         $condition = "id = CAST({$this->newSequiDocsDocumentArray['id']} AS INT64)"; // WHERE condition
    //         Log::info("Condition for update: $condition");
    //         $updates = $this->newSequiDocsDocumentArray;
    //         $ColumnArray = ['mobile_no', 'worker_type', 'signature_request_id', 'signature_request_document_id'];
    //         $bigQueryResponse = $bigQueryService->updateRecords('sequifi', 'new_sequi_docs_documents', $condition, $updates, $ColumnArray);
    //     }
    // }
}
