<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\JobNotificationService;
use App\Services\NotificationService;
use App\Services\PositionUpdateNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class NotificationsChangesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use an in-memory sqlite DB just for unit testing.
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Schema::connection('sqlite')->dropAllTables();

        Schema::connection('sqlite')->create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedTinyInteger('is_super_admin')->default(0);
            $table->unsignedTinyInteger('dismiss')->default(0);
            $table->unsignedTinyInteger('terminate')->default(0);
            $table->unsignedTinyInteger('contract_ended')->default(0);
        });
    }

    public function test_job_notifications_include_initiator_and_all_active_super_admins(): void
    {
        // Arrange: 2 active super admins + 1 normal initiator
        \DB::table('users')->insert([
            ['id' => 1, 'is_super_admin' => 1, 'dismiss' => 0, 'terminate' => 0, 'contract_ended' => 0],
            ['id' => 2, 'is_super_admin' => 1, 'dismiss' => 0, 'terminate' => 0, 'contract_ended' => 0],
            ['id' => 3, 'is_super_admin' => 0, 'dismiss' => 0, 'terminate' => 0, 'contract_ended' => 0],
        ]);

        $storedFor = [];

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('notificationExists')->andReturn(false);
        $notificationService->shouldReceive('storeNotification')
            ->andReturnUsing(function (int $userId, string $type, array $data) use (&$storedFor): bool {
                $storedFor[] = $userId;
                return true;
            });

        // Not used for sales_excel_import (shouldEmitPositionUpdateUx=false), but required by ctor.
        $positionUpdateService = Mockery::mock(PositionUpdateNotificationService::class);

        $svc = new JobNotificationService($notificationService, $positionUpdateService);

        // Act
        $svc->notify(
            recipientUserId: 3,
            type: 'sales_excel_import',
            job: 'Sales import',
            status: 'started',
            progress: 0,
            message: 'Queued',
            uniqueKey: 'unit_test_key',
            initiatedAt: now()->toIso8601String(),
        );

        // Assert
        sort($storedFor);
        $storedFor = array_values(array_unique($storedFor));
        $this->assertSame([1, 2, 3], $storedFor);
    }

    public function test_notifications_active_falls_back_to_keys_when_scan_returns_no_keys(): void
    {
        config()->set('database.redis.options.prefix', 'turfstage_database_');

        $userId = 57;
        $prefixedKey = 'turfstage_database_notifications:'.$userId.':debug:abc';
        $rawKey = 'notifications:'.$userId.':debug:abc';

        // SCAN returns nothing (simulating client/cluster-mode issues)
        Redis::shouldReceive('scan')
            ->andReturn(['0', []]);

        // Fallback path should use KEYS and then GET each key
        Redis::shouldReceive('keys')
            ->andReturn([$prefixedKey]);

        Redis::shouldReceive('get')
            ->with($rawKey)
            ->andReturn(json_encode([
                'type' => 'debug',
                'job' => 'Debug',
                'status' => 'started',
                'progress' => 0,
                'message' => 'debug notification',
                'recipientUserIds' => [$userId],
                'initiatedAt' => now()->toIso8601String(),
                'completedAt' => null,
                'uniqueKey' => 'abc',
                'meta' => [],
                'timestamp' => now()->toISOString(),
            ]));

        $svc = new NotificationService();
        $result = $svc->getActiveNotifications($userId, null);

        $this->assertCount(1, $result);
        $this->assertSame('debug', $result[0]['type'] ?? null);
        $this->assertSame([$userId], $result[0]['recipientUserIds'] ?? null);
    }
}

