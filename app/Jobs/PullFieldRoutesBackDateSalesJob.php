<?php

namespace App\Jobs;

/**
 * Job to pull back-dated sales data from FieldRoutes when a user's hiring date changes
 *
 * This job runs asynchronously to avoid blocking the web request when updating hire dates.
 * It pulls sales data from the new hire date forward to ensure all sales are captured.
 *
 * It performs the following steps:
 * 1. Gets subscriptions from FieldRoutes API for each office the user works in
 * 2. Syncs the data to the legacy system format for sales processing
 *
 * @author System
 *
 * @since 2025-01-01
 */

use App\Models\FrEmployeeData;
use App\Services\JobNotificationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class PullFieldRoutesBackDateSalesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user ID to pull sales for
     */
    public $userId;

    /**
     * The new hire date
     */
    public $newHireDate;

    /**
     * The old hire date
     */
    public $oldHireDate;

    public string $notificationUniqueKey;

    public string $notificationInitiatedAt;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public $timeout = 1800; // 30 minutes

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $newHireDate, $oldHireDate)
    {
        $this->userId = $userId;
        $this->newHireDate = $newHireDate;
        $this->oldHireDate = $oldHireDate;
        $this->notificationInitiatedAt = now()->toIso8601String();
        $this->notificationUniqueKey = 'fieldroutes_backdate_' . (int)$userId . '_' . time();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            app(JobNotificationService::class)->notify(
                (int) $this->userId,
                'fieldroutes_backdate_sales',
                'PullFieldRoutesBackDateSalesJob',
                'started',
                0,
                'Backdated FieldRoutes sales pull started.',
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                null,
                [
                    'user_id' => (int) $this->userId,
                    'new_hire_date' => $this->newHireDate,
                    'old_hire_date' => $this->oldHireDate,
                ]
            );

            // Get employee office data
            $employeeOffices = FrEmployeeData::select('employee_id', 'sequifi_id', 'office_name', 'office_id')
                ->where('sequifi_id', $this->userId)
                ->get();

            if ($employeeOffices->isEmpty()) {
                Log::error("No employee office found for user {$this->userId}");
                $this->fail(new Exception("No employee office found for user {$this->userId}"));

                return;
            }

            $successfulOffices = 0;

            // Step 1: Get subscriptions for each office/employee
            $officeCount = $employeeOffices->count();
            $processed = 0;
            foreach ($employeeOffices as $office) {
                try {

                    $exitCode = Artisan::call('fieldroutes:get-subscriptions', [
                        'from_date' => $this->newHireDate,
                        'to_date' => $this->oldHireDate,
                        '--office' => $office->office_name,
                        '--employee' => $office->employee_id,
                        '--all' => true,
                        '--save' => true,
                    ]);

                    if ($exitCode === 0) {
                        $successfulOffices++;
                    } else {
                        Log::error("Failed to get subscriptions for office: {$office->office_name}, employee: {$office->employee_id}");
                    }
                } catch (Exception $e) {
                    Log::error("Exception while processing office {$office->office_name}: ".$e->getMessage());
                }

                $processed++;
                if ($officeCount > 0) {
                    $progress = (int) round(($processed / $officeCount) * 70);
                    app(JobNotificationService::class)->notify(
                        (int) $this->userId,
                        'fieldroutes_backdate_sales',
                        'PullFieldRoutesBackDateSalesJob',
                        'processing',
                        $progress,
                        "Fetching subscriptions ({$processed}/{$officeCount})...",
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        null,
                        [
                            'successful_offices' => $successfulOffices,
                            'office_count' => $officeCount,
                        ]
                    );
                }
            }

            // Step 2: Sync data using Sequifi user ID
            if ($successfulOffices > 0) {
                try {
                    app(JobNotificationService::class)->notify(
                        (int) $this->userId,
                        'fieldroutes_backdate_sales',
                        'PullFieldRoutesBackDateSalesJob',
                        'processing',
                        85,
                        'Syncing FieldRoutes data...',
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        null,
                        [
                            'successful_offices' => $successfulOffices,
                        ]
                    );

                    Artisan::call('fieldroutes:sync-data', [
                        '--user-ids' => $this->userId,
                    ]);
                } catch (Exception $e) {
                    Log::error("Exception while syncing data for user {$this->userId}: ".$e->getMessage());
                    $this->fail($e);
                }
            } else {
                Log::error("No offices processed successfully for user {$this->userId}. Skipping data sync.");
                $this->fail(new Exception("No offices processed successfully for user {$this->userId}"));
            }

            app(JobNotificationService::class)->notify(
                (int) $this->userId,
                'fieldroutes_backdate_sales',
                'PullFieldRoutesBackDateSalesJob',
                'completed',
                100,
                'Backdated FieldRoutes sales pull completed.',
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                now()->toIso8601String(),
                [
                    'successful_offices' => $successfulOffices,
                ]
            );
        } catch (Exception $e) {
            Log::error("Error in PullFieldRoutesBackDateSalesJob for user {$this->userId}: ".$e->getMessage());
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error("PullFieldRoutesBackDateSalesJob failed for user {$this->userId}: ".$exception->getMessage());

        // Persist failure notification (best-effort)
        try {
            app(JobNotificationService::class)->notify(
                (int) $this->userId,
                'fieldroutes_backdate_sales',
                'PullFieldRoutesBackDateSalesJob',
                'failed',
                0,
                'Backdated FieldRoutes sales pull failed: ' . $exception->getMessage(),
                $this->notificationUniqueKey ?? ('fieldroutes_backdate_' . (int)$this->userId . '_' . time()),
                $this->notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                now()->toIso8601String(),
                [
                    'user_id' => (int) $this->userId,
                ]
            );
        } catch (\Throwable $ignore) {
            // never fail the failed() handler
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['fieldroutes', 'backdate-sales-pull', "user:{$this->userId}"];
    }
}
