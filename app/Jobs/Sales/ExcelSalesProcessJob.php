<?php

namespace App\Jobs\Sales;

use App\Traits\EmailNotificationTrait;
use App\Services\JobNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExcelSalesProcessJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    public $user;

    public $excel;

    public string $notificationUniqueKey;

    public string $notificationInitiatedAt;

    /**
     * The number of seconds after which the job's unique lock will be released.
     * Must match or exceed retry_after (7200s) to prevent duplicate jobs during long imports.
     *
     * @var int
     */
    public $uniqueFor = 7200;

    public function __construct($user, $excel, ?string $notificationUniqueKey = null, ?string $notificationInitiatedAt = null)
    {
        $this->user = $user;
        $this->excel = $excel;
        $this->notificationInitiatedAt = is_string($notificationInitiatedAt) && $notificationInitiatedAt !== ''
            ? $notificationInitiatedAt
            : now()->toIso8601String();
        $this->notificationUniqueKey = is_string($notificationUniqueKey) && $notificationUniqueKey !== ''
            ? $notificationUniqueKey
            : ('excel_sales_' . ($excel->id ?? 'unknown') . '_' . time());
        $this->onQueue('sales-process');
    }

    /**
     * Get the unique ID for the job.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return 'excel_process_job_' . $this->excel->id;
    }

    public function handle(): void
    {
        try {
            $recipientUserId = (int) ($this->user->id ?? 0);
            app(JobNotificationService::class)->notify(
                $recipientUserId,
                'sales_excel_import',
                'ExcelSalesProcessJob',
                'processing',
                0,
                'Excel sales import processing.',
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                null,
                [
                    'excel_id' => $this->excel->id ?? null,
                    'phase' => 'processing',
                ]
            );

            // Log detailed information at the start of job handling
            Log::info('ExcelSalesProcessJob starting execution', [
                'type' => 'excel',
                'attempt' => $this->attempts(),
                'queue' => $this->queue ?? 'unknown',
                'memory_usage_start' => memory_get_usage(true) / 1024 / 1024 .'MB',
            ]);

            // Explicitly reconnect to the database to prevent connection timeout issues
            DB::disconnect('mysql');
            DB::reconnect('mysql');
            Log::info('ExcelSalesProcessJob: Reconnected to database');

            $namespace = app()->getNamespace();
            $salesController = app()->make($namespace.\Http\Controllers\API\V2\Sales\SalesExcelProcessController::class);

            // Pass the callback + notification identifiers so the controller can emit
            // progress updates for the SAME sales_excel_import notification card.
            $result = $salesController->salesExcelCompleteProcess(
                $this->user,
                $this->excel,
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt
            );
            if ($result === false) {
                throw new \RuntimeException('Sales import failed.');
            }
            Log::info('Completed ExcelSalesProcessJob for excel');
            // NOTE: For CSV imports, `SalesExcelProcessController::salesExcelCompleteProcess()` now drives:
            // - Sales import: started -> processing (0..99) -> completed (100)
            // - Sales processing: started -> processing (0..99) -> completed (100)
            // - Sales recalculation: started -> processing (0..99) -> completed (100)
            // So we intentionally do NOT emit a second "completed(100)" here to avoid duplicate cards / racey UX.
        } catch (\Throwable $e) {
            // Enhanced error logging with more context
            Log::error('ExcelSalesProcessJob failed with exception', [
                'type' => 'excel',
                'attempt' => $this->attempts(),
                'queue' => $this->queue ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 .'MB',
            ]);

            // Check if this is a database connection issue
            $errorMsg = $e->getMessage();

            if (
                str_contains($errorMsg, 'SQLSTATE[HY000]') ||
                str_contains($errorMsg, 'Error while reading greeting packet') ||
                str_contains($errorMsg, 'Lost connection') ||
                str_contains($errorMsg, 'gone away')
            ) {
                Log::error('ExcelSalesProcessJob database connection error detected', [
                    'error' => $errorMsg,
                    'type' => 'excel',
                    'attempt' => $this->attempts(),
                ]);

                // Attempt to reconnect before giving up
                try {
                    DB::disconnect('mysql');
                    DB::reconnect('mysql');
                    Log::info('ExcelSalesProcessJob attempted emergency database reconnection');
                } catch (\Exception $reconnectError) {
                    Log::error('Failed emergency database reconnection', [
                        'error' => $reconnectError->getMessage(),
                    ]);
                }
            }

            // Re-throw to trigger the failed method
            throw $e;
        }
    }

    public function failed(\Throwable $e)
    {
        // Log when job is definitively considered failed by Laravel
        Log::error('ExcelSalesProcessJob marked as FAILED by Laravel queue system', [
            'type' => 'excel',
            'excel_id' => $this->excel->id ?? null,
            'final_attempt' => $this->attempts(),
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'memory_usage_final' => memory_get_usage(true) / 1024 / 1024 .'MB',
        ]);

        // Update Excel Import History with failure information
        if (isset($this->excel->id)) {
            try {
                $errorDetails = [
                    'error_type' => 'job_failed',
                    'message' => 'Import processing failed: The background job encountered an unexpected error. Please contact support if this persists.',
                    'technical_details' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'timestamp' => now()->toIso8601String(),
                ];

                \App\Models\ExcelImportHistory::where('id', $this->excel->id)->update([
                    'status' => 2, // Failed
                    'errors' => json_encode($errorDetails),
                ]);
            } catch (\Throwable $updateError) {
                Log::error('Failed to update ExcelImportHistory after job failure', [
                    'excel_id' => $this->excel->id,
                    'update_error' => $updateError->getMessage(),
                ]);
            }
        }

        // Persist failure notification (best-effort)
        try {
            $recipientUserId = (int) ($this->user->id ?? 0);
            app(JobNotificationService::class)->notify(
                $recipientUserId,
                'sales_excel_import',
                'ExcelSalesProcessJob',
                'failed',
                0,
                'Excel sales import failed: ' . $e->getMessage(),
                $this->notificationUniqueKey ?? ('excel_sales_' . ($this->excel->id ?? 'unknown') . '_' . time()),
                $this->notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                now()->toIso8601String(),
                [
                    'excel_id' => $this->excel->id ?? null,
                ]
            );
        } catch (\Throwable $ignore) {
            // never fail the failed() handler
        }
    }
}
