<?php

namespace App\Jobs;

use App\Jobs\Sales\ProcessRecalculatesOpenSales;
use App\Models\SalesMaster;
use App\Models\UserCommission;
use App\Services\JobNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateOpenTieredSalesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $pid;
    protected string $notificationUniqueKey;
    protected string $notificationInitiatedAt;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($pid = '')
    {
        $this->pid = $pid;
        $this->notificationInitiatedAt = now()->toIso8601String();
        $this->notificationUniqueKey = 'recalc_open_tiered_' . ($pid ?: 'unknown') . '_' . time();
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        app(JobNotificationService::class)->notify(
            null,
            'sales_recalculate_open_tiered',
            'RecalculateOpenTieredSalesJob',
            'started',
            0,
            'Open tiered sales recalculation check started.',
            $this->notificationUniqueKey,
            $this->notificationInitiatedAt,
            null,
            [
                'trigger_pid' => $this->pid,
            ]
        );

        $m2Paid = UserCommission::where(['pid' => $this->pid, 'is_last' => '1', 'status' => 3, 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
        $reconM2Paid = UserCommission::where(['pid' => $this->pid, 'is_last' => '1', 'recon_status' => 3, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->first();
        if ($m2Paid || $reconM2Paid) {
            app(JobNotificationService::class)->notify(
                null,
                'sales_recalculate_open_tiered',
                'RecalculateOpenTieredSalesJob',
                'completed',
                100,
                'Skipped: final commission already paid.',
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                now()->toIso8601String()
            );
            return 'Final Commission have been paid!!';
        }

        $saleData = SalesMaster::with('salesMasterProcessInfo')->where('pid', $this->pid)->first();
        if (! $saleData) {
            app(JobNotificationService::class)->notify(
                null,
                'sales_recalculate_open_tiered',
                'RecalculateOpenTieredSalesJob',
                'failed',
                0,
                'Sale not found.',
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                now()->toIso8601String()
            );
            return 'Sale not found!!';
        }

        $saleUsers = [];
        if ($saleData->salesMasterProcessInfo->closer1_id) {
            $saleUsers[] = $saleData->salesMasterProcessInfo->closer1_id;
        }
        if ($saleData->salesMasterProcessInfo->closer2_id) {
            $saleUsers[] = $saleData->salesMasterProcessInfo->closer2_id;
        }
        if ($saleData->salesMasterProcessInfo->setter1_id) {
            $saleUsers[] = $saleData->salesMasterProcessInfo->setter1_id;
        }
        if ($saleData->salesMasterProcessInfo->setter2_id) {
            $saleUsers[] = $saleData->salesMasterProcessInfo->setter2_id;
        }

        if (count($saleUsers) != 0) {
            $m2Paid = UserCommission::where(['is_last' => '1', 'status' => 3, 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->pluck('pid');
            $reconM2Paid = UserCommission::where(['is_last' => '1', 'recon_status' => 3, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->pluck('pid');
            $paidSale = array_merge($m2Paid->toArray(), $reconM2Paid->toArray());
            $pid = SalesMaster::whereHas('salesMasterProcessInfo', function ($q) use ($saleUsers) {
                $q->where(function ($q) use ($saleUsers) {
                    $q->whereIn('closer1_id', $saleUsers)->orWhereIn('setter1_id', $saleUsers)->orWhereIn('closer2_id', $saleUsers)->orWhereIn('setter2_id', $saleUsers);
                });
            })->whereNotIn('pid', $paidSale)->whereNull('date_cancelled')->pluck('pid');
            if (count($pid) != 0) {
                // Create unique lock key based on sorted PIDs to prevent duplicate dispatches
                $lockKey = 'recalculate_sales_' . md5(implode(',', $pid->sort()->toArray()));
                
                // Only dispatch if not already processing (5 minute lock)
                if (\Illuminate\Support\Facades\Cache::add($lockKey, true, 300)) {
                    ProcessRecalculatesOpenSales::dispatch($pid, []);
                    \Log::info('[RecalculateOpenTieredSalesJob] Dispatched ProcessRecalculatesOpenSales', [
                        'lock_key' => $lockKey,
                        'pid_count' => count($pid),
                        'trigger_pid' => $this->pid,
                    ]);

                    app(JobNotificationService::class)->notify(
                        null,
                        'sales_recalculate_open_tiered',
                        'RecalculateOpenTieredSalesJob',
                        'completed',
                        100,
                        'Dispatched open sales recalculation job.',
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        now()->toIso8601String(),
                        [
                            'lock_key' => $lockKey,
                            'pid_count' => count($pid),
                            'trigger_pid' => $this->pid,
                        ]
                    );
                } else {
                    \Log::info('[RecalculateOpenTieredSalesJob] Skipped duplicate dispatch', [
                        'lock_key' => $lockKey,
                        'pid_count' => count($pid),
                        'trigger_pid' => $this->pid,
                    ]);

                    app(JobNotificationService::class)->notify(
                        null,
                        'sales_recalculate_open_tiered',
                        'RecalculateOpenTieredSalesJob',
                        'completed',
                        100,
                        'Skipped duplicate dispatch (already in progress).',
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        now()->toIso8601String(),
                        [
                            'lock_key' => $lockKey,
                            'pid_count' => count($pid),
                            'trigger_pid' => $this->pid,
                        ]
                    );
                }
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        try {
            app(JobNotificationService::class)->notify(
                null,
                'sales_recalculate_open_tiered',
                'RecalculateOpenTieredSalesJob',
                'failed',
                0,
                'Open tiered sales recalculation job failed: ' . $exception->getMessage(),
                $this->notificationUniqueKey ?? ('recalc_open_tiered_' . ($this->pid ?: 'unknown') . '_' . time()),
                $this->notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                now()->toIso8601String(),
                [
                    'trigger_pid' => $this->pid,
                ]
            );
        } catch (\Throwable $ignore) {
            // never fail failed() handler
        }
    }
}
