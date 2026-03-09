<?php

namespace App\Console\Commands;

use App\Services\ClarkExcelImportService;
use App\Services\SentryMonitoring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Sentry\CheckInStatus;

class ImportClarkExcelFiles extends Command
{
    protected $signature = 'import:clark-excel
        {--batch=1000 : Number of records to process in each batch}
        {--memory-limit=2048 : Memory limit in MB}
        {--timeout=3600 : Maximum execution time in seconds}
        {--optimize-memory : Use optimized memory mode for very large files}';

    protected $description = 'Import Excel files from Clark-momentum bucket and map to LegacyApiRawDataHistory';

    private $excelService;

    private SentryMonitoring $sentryMonitoring;

    public function __construct(ClarkExcelImportService $excelService, SentryMonitoring $sentryMonitoring)
    {
        parent::__construct();
        $this->excelService = $excelService;
        $this->sentryMonitoring = $sentryMonitoring;
    }

    public function handle(): void
    {
        $checkInId = $this->sentryMonitoring->startMonitoring('scheduled_artisan-import-clark-excel-batch1000-mem', 'Clark Excel Import');

        try {
            $this->sentryMonitoring->updateStatus($checkInId, CheckInStatus::inProgress());
            $stats = $this->excelService->processExcelFiles(output: $this->output);

            $this->info('Import completed successfully!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Files', $stats['total_files']],
                    ['Processed Files', $stats['processed_files']],
                    ['Total Records', $stats['total_records']],
                    ['New Records', $stats['processed_records']],
                    ['Updated Records', $stats['updated_records']],
                    ['Skipped Records', $stats['skipped_records']],
                    ['Error Records', $stats['error_records']],
                ]
            );

            $this->sentryMonitoring->updateStatus($checkInId, CheckInStatus::ok());
        } catch (\Exception $e) {
            $this->error('Error importing Clark Excel files: '.$e->getMessage());
            Log::error('Error importing Clark Excel files', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->sentryMonitoring->updateStatus($checkInId, CheckInStatus::error());
            throw $e;
        }
    }
}
