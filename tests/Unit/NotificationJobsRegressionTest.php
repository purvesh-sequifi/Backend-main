<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\API\V2\Sales\SalesProcessController;
use App\Jobs\RecalculateSalesJob;
use App\Jobs\Sales\SaleProcessJob;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class NotificationJobsRegressionTest extends TestCase
{
    public function test_recalculate_sales_job_defines_get_batch_totals(): void
    {
        self::assertTrue(
            method_exists(RecalculateSalesJob::class, 'getBatchTotals'),
            'RecalculateSalesJob must define getBatchTotals() because failed() calls it.'
        );
    }

    public function test_sale_process_job_batch_mode_does_not_fail_when_redis_throws(): void
    {
        // Arrange: make all Redis operations throw.
        Redis::shouldReceive('exists')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('set')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('get')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('incr')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('expire')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('setex')->andThrow(new \RuntimeException('redis down'));

        // Prevent real DB work: the job should still reach the controller call.
        $this->mock(SalesProcessController::class, function ($mock): void {
            $mock->shouldReceive('integrationSaleProcess')
                ->once()
                ->andReturnNull();
        });

        $job = new SaleProcessJob(
            ids: [1, 2, 3],
            batchNotificationKey: 'sale_process_batch_test_key',
            batchInitiatedAt: now()->toIso8601String(),
            chunkNumber: 1,
            totalChunks: 2,
            totalRecords: 3,
            batchSize: 2,
            dataSourceType: 'Clark'
        );

        // Act + Assert: handle() should not throw even if Redis is unavailable.
        $job->handle();
        self::assertTrue(true);
    }

    public function test_sale_process_job_failed_handler_does_not_throw_when_redis_throws(): void
    {
        Redis::shouldReceive('exists')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('get')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('incr')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('expire')->andThrow(new \RuntimeException('redis down'));

        $job = new SaleProcessJob(
            ids: [1],
            batchNotificationKey: 'sale_process_batch_test_key',
            batchInitiatedAt: now()->toIso8601String(),
            chunkNumber: 1,
            totalChunks: 1,
            totalRecords: 1,
            batchSize: 1,
            dataSourceType: 'Clark'
        );

        $job->failed(new \RuntimeException('boom'));
        self::assertTrue(true);
    }
}

