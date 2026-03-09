<?php

namespace App\Console\Commands;

use App\Services\ClarkExcelImportService;
use App\Services\SentryMonitoring;
use App\Services\SftpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Sentry\CheckInStatus;

class ImportClarkExcelSftpFiles extends Command
{
    protected $signature = 'import:clark-excel-sftp
        {--batch=1000 : Number of records to process in each batch}
        {--memory-limit=2048 : Memory limit in MB}
        {--timeout=3600 : Maximum execution time in seconds}';

    protected $description = 'Import Clark Excel files from SFTP';

    /** @var string Sentry monitoring check-in ID */
    protected $checkInId;

    /** @var ClarkExcelImportService */
    protected $importService;

    /** @var SftpService */
    protected $sftpService;

    /** @var SentryMonitoring */
    protected $sentryMonitoring;

    public function __construct(ClarkExcelImportService $importService, SftpService $sftpService, SentryMonitoring $sentryMonitoring)
    {
        parent::__construct();
        $this->importService = $importService;
        $this->sftpService = $sftpService;
        $this->sentryMonitoring = $sentryMonitoring;
    }

    public function handle(): int
    {
        // Configure PHP execution time limits
        $maxExecutionTime = (int) $this->option('timeout');
        ini_set('max_execution_time', $maxExecutionTime);
        set_time_limit($maxExecutionTime);

        // Configure memory limit
        $memoryLimit = (int) $this->option('memory-limit');
        ini_set('memory_limit', $memoryLimit.'M');

        // Initialize Sentry monitoring
        $this->checkInId = $this->sentryMonitoring->startMonitoring(
            'scheduled_artisan-import-clark-excel-batch1000-mem',
            'Clark Excel SFTP Import'
        );
        $this->sentryMonitoring->updateStatus($this->checkInId, CheckInStatus::inProgress());

        try {
            $this->info('Starting Clark Excel SFTP import...');

            $stats = $this->importService->processExcelFiles('/clark/excel');

            $this->info('Import completed successfully:');
            $this->info("Total files: {$stats['total_files']}");
            $this->info("Processed files: {$stats['processed_files']}");
            $this->info("Total records: {$stats['total_records']}");
            $this->info("Processed records: {$stats['processed_records']}");
            $this->info("Skipped records: {$stats['skipped_records']}");
            $this->info("Error files: {$stats['error_files']}");
            $this->info("Error records: {$stats['error_records']}");
            $this->info("Updated records: {$stats['updated_records']}");

            // Update Sentry status to success
            $this->sentryMonitoring->updateStatus($this->checkInId, CheckInStatus::ok());

            return 0;

        } catch (\Exception $e) {
            Log::error('Clark Excel SFTP import failed: '.$e->getMessage());
            $this->error('Import failed: '.$e->getMessage());

            // Update Sentry status to error
            $this->sentryMonitoring->updateStatus($this->checkInId, CheckInStatus::error());

            return 1;
        }
    }
}
