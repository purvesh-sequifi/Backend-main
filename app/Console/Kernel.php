<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application...
     *
     * @var array
     */
    protected $commands = [
        Commands\SyncSingleUserToBigQuery::class,
        \App\Console\Commands\EnsureClickhouseActivityLog::class,
        \App\Console\Commands\SyncClickhouseActivityLog::class,
        \App\Console\Commands\ClickHouseHeartbeat::class,
        \App\Console\Commands\QueueClearCommand::class,
        \App\Console\Commands\GenerateSwaggerDocumentation::class,
        \App\Console\Commands\GenerateCompleteSwaggerDocs::class,
        \App\Console\Commands\MonitorJobsCommand::class,
        \App\Console\Commands\GenerateSegregatedSwaggerDocs::class,
        \App\Console\Commands\TestUpdate::class,
        \App\Console\Commands\TrackSchedulerChanges::class,
        \App\Console\Commands\LogLegacyApiData::class,
        \App\Console\Commands\CleanStuckJobsCommand::class,
        \App\Console\Commands\ImportSftpJsonFiles::class,
        \App\Console\Commands\CleanupJobPerformanceLogs::class,
        \App\Console\Commands\ImportSftpJsonFiles::class,
        \App\Console\Commands\ImportClarkExcelFiles::class,
        \App\Console\Commands\ImportClarkExcelSftpFiles::class,
        \App\Console\Commands\FetchEvereePaymentStatusesCommand::class,
        \App\Console\Commands\UpdatePidFromPocomos::class,
        \App\Console\Commands\PocomosRawDataSync::class,
        Commands\AnalyzeFieldRoutesDataCommand::class,
        \App\Console\Commands\ProcessAutomationLog::class,
        \App\Console\Commands\V2\PayrollReCalculateCommand::class,
        \App\Console\Commands\V2\PayrollSalaryCalculateCommand::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ClickHouse heartbeat to prevent deep sleep issues dfssdfsd as
        // Run every 5 minutes with extended timeout and retry settings
        $schedule->command('clickhouse:heartbeat --max-retries=7 --initial-timeout=120 --deep-sleep-mode')
            ->everyFiveMinutes()
            ->runInBackground()
            ->withoutOverlapping(10) // Prevent overlapping runs for up to 10 minutes
            ->appendOutputTo(storage_path('logs/clickhouse-heartbeat.log'));

        // ClickHouse initial setup and massive data migration (50GB+)
        // This runs once when ClickHouse is empty to perform the initial migration
        // After successful migration, this will skip automatically
        $schedule->command('clickhouse:ensure-activity-log-table --batch-size=8000 --max-retries=5')
            ->dailyAt('02:00') // Run at 2 AM to avoid peak hours
            ->timezone('UTC')
            ->withoutOverlapping(180) // Allow up to 3 hours for massive migration
            ->appendOutputTo(storage_path('logs/clickhouse-initial-setup.log'))
            ->when(function () {
                // Only run if ClickHouse activity_log table is empty (first-time setup)
                try {
                    $client = \App\Services\ClickHouseConnectionService::getClient();
                    if (! $client) {
                        return false;
                    }
                    $count = (int) $client->select('SELECT count() as cnt FROM activity_log')->rows()[0]['cnt'];
                    Log::info('[ClickHouse Scheduler] Initial setup condition check', [
                        'clickhouse_count' => $count,
                        'will_run' => $count === 0,
                    ]);

                    return $count === 0; // Only run if ClickHouse is empty
                } catch (\Exception $e) {
                    Log::error('[ClickHouse Scheduler] Setup condition check failed', ['error' => $e->getMessage()]);

                    return false;
                }
            })
            ->before(function () {
                Log::info('[ClickHouse Scheduler] Starting massive 50GB+ initial migration', [
                    'timestamp' => now()->toIso8601String(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                ]);
            })
            ->after(function () {
                Log::info('[ClickHouse Scheduler] Initial migration completed successfully', [
                    'timestamp' => now()->toIso8601String(),
                    'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 .'MB',
                ]);
            })
            ->onFailure(function () {
                Log::error('[ClickHouse Scheduler] Initial migration failed, can be resumed with --resume flag', [
                    'timestamp' => now()->toIso8601String(),
                    'recovery_command' => 'php artisan clickhouse:ensure-activity-log-table --resume',
                ]);
            });

        // Nightly ClickHouse activity log sync (improved version)
        $schedule->command('clickhouse:sync-activity-log --batch-size=5000')
            ->dailyAt('23:55')
            ->timezone('UTC')
            ->withoutOverlapping(30) // Prevent overlapping for up to 30 minutes
            ->appendOutputTo(storage_path('logs/clickhouse-sync.log'))
            ->onFailure(function () {
                Log::error('[ClickHouse Scheduler] Nightly sync failed, will attempt recovery');
            });

        // ClickHouse heartbeat monitoring (already configured above)
        // Track scheduler changes and notify Slack when new schedulers are added in UAT environment
        // $schedule->command('scheduler:track-changes --notify-slack')->daily();

        // Regenerate API documentation daily
        // $schedule->command('swagger:generate-segregated')->dailyAt('01:00');

        $schedule->command('userAttendanceSet:update')->dailyAt('23:30');
        // Import Clark Excel files from SFTP
        // $schedule->command('import:clark-excel-sftp')->everyThirtyMinutes()
        //     ->withoutOverlapping()
        //     ->runInBackground();

        // BigQuery integration synchronization tasks
        // $schedule->command('usersynconbigquery:hourly')
        //     ->hourly()
        //     ->withoutOverlapping()
        //     ->when(function () {
        //         // Check if BigQuery integration is enabled in the database
        //         return DB::table('integrations')->where('name', 'BigQuery')->where('status', 1)->exists();
        //     })
        //     ->sentryMonitor(
        //         checkInMargin: 15,
        //         maxRuntime: 30,
        //         failureIssueThreshold: 1
        //     );

        // $schedule->command('leadssynconbigquery:hourly')
        //     ->hourly()
        //     ->withoutOverlapping()
        //     ->when(function () {
        //         // Check if BigQuery integration is enabled in the database
        //         return DB::table('integrations')->where('name', 'BigQuery')->where('status', 1)->exists();
        //     })
        //     ->sentryMonitor(
        //         checkInMargin: 15,
        //         maxRuntime: 30,
        //         failureIssueThreshold: 1
        //     );

        // $schedule->command('syncadditionalinfoforemployeeonbigquery:hourly')
        //     ->hourly()
        //     ->withoutOverlapping()
        //     ->when(function () {
        //         // Check if BigQuery integration is enabled in the database
        //         return DB::table('integrations')->where('name', 'BigQuery')->where('status', 1)->exists();
        //     })
        //     ->sentryMonitor(
        //         checkInMargin: 15,
        //         maxRuntime: 30,
        //         failureIssueThreshold: 1
        //     );

        // $schedule->command('syncnewsequidocsdocumentonbigquery:hourly')
        //     ->hourly()
        //     ->withoutOverlapping()
        //     ->when(function () {
        //         // Check if BigQuery integration is enabled in the database
        //         return DB::table('integrations')->where('name', 'BigQuery')->where('status', 1)->exists();
        //     })
        //     ->sentryMonitor(
        //         checkInMargin: 15,
        //         maxRuntime: 30,
        //         failureIssueThreshold: 1
        //     );

        $schedule->command('legacy:insert')->everySixHours();
        // REMOVED: Orphaned queue worker that was creating problematic mixed-queue workers
        // $schedule->command('queue:work --queue=parlley,special-import,special,default --stop-when-empty')->hourly();
        $schedule->command('manualEffectiveDate:update')->dailyAt('00:30');
        $schedule->command('OfferExpiryDate:StatusUpdate')->dailyAt('00:30');
        // $schedule->command('jobnimbus:jobs-sync')->dailyAt('00:30');
        // $schedule->command('pocomos:insert --batch=500 --memory-limit=2048 --timeout=3600')
        //     ->everyThirtyMinutes()
        //     ->withoutOverlapping()
        //     ->when(function () {
        //         // Check if any integration with status=1 exists
        //         return DB::table('integrations')->where('name', 'Pocomos')->where('status', 1)->exists();
        //     })
        //     ->sentryMonitor(
        //         checkInMargin: 15,
        //         maxRuntime: 60,  // Adjusted to match command timeout of 3600 seconds (60 minutes)
        //         failureIssueThreshold: 1
        //     );
        // $schedule->command('hawxUserTerminate:update')->dailyAt('00:05');

        // $schedule->command('jira:sync')->everyThirtyMinutes(); // Syncs Status & Estimation Date From Jira!!
        // $schedule->command('digisigner_signed_status_for_users:update')->everyFourHours(); // update digisigner signed status for users
        $schedule->command('genratebilling:history')->monthlyOn(1, '00:30');
        $schedule->command('genrateWeeklyBilling:history')->weeklyOn(1, '00:30');
        $schedule->command('SignStatus:sync')->everyTwoHours();
        $schedule->command('syncSalesProjectionData:sync')->dailyAt('05:00')->timezone('UTC');
        // Schedule the dismissal command to run yearly on September 30th at midnight
        // $schedule->command('seasonalUsers:dismiss')->yearlyOn(9, 30, '00:00');
        $schedule->command('seasonalUsers:dismissByContractDate')->dailyAt('00:00');
        // Enhanced automation scheduling with safety measures and monitoring
        $schedule->command('automation:run --optimize-memory --batch-size=100')
            ->everyTenMinutes() // Keep current 10-minute schedule from production
            ->withoutOverlapping(15) // Prevent overlapping runs for up to 15 minutes
            ->runInBackground() // Run in background to prevent blocking other tasks
            ->appendOutputTo(storage_path('logs/automation-scheduler.log'))
            ->when(function () {
                // Enhanced condition check with context-aware monitoring
                $pendingCount = DB::table('automation_action_logs')
                    ->whereNull('deleted_at')
                    ->where(function ($query) {
                        $query->where('email_sent', 0)
                            ->orWhere('status', 0);
                    })
                    ->count();

                Log::debug('Automation scheduler condition check', [
                    'pending_automation_logs' => $pendingCount,
                    'will_run' => $pendingCount > 0,
                ]);

                return $pendingCount > 0;
            })
            ->before(function () {
                Log::info('Automation scheduler starting', [
                    'timestamp' => now()->toIso8601String(),
                    'memory_usage' => memory_get_usage(true) / 1024 / 1024 .'MB',
                ]);
            })
            ->after(function () {
                Log::info('Automation scheduler completed', [
                    'timestamp' => now()->toIso8601String(),
                    'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 .'MB',
                ]);
            })
            ->onFailure(function () {
                Log::error('Automation scheduler failed', [
                    'timestamp' => now()->toIso8601String(),
                    'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 .'MB',
                ]);
            });

        // Process pending automation logs separately for better performance
        $schedule->command('automation:process-logs --batch-size=150')
            ->everyFiveMinutes() // Process logs at half the frequency of main automation
            ->withoutOverlapping(8) // Appropriate overlap prevention for 5-minute schedule
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/automation-log-processor.log'))
            ->when(function () {
                // Enhanced condition check for context-aware automation logs
                $unprocessedCount = DB::table('automation_action_logs')
                    ->whereNull('deleted_at')
                    ->where('email_sent', 0)
                    ->where('status', 1) // Ready to be processed
                    ->whereNotNull('email') // Has email data
                    ->count();

                if ($unprocessedCount > 0) {
                    Log::debug('Automation log processor condition check', [
                        'unprocessed_logs' => $unprocessedCount,
                        'will_run' => true,
                    ]);
                }

                return $unprocessedCount > 0;
            })
            ->before(function () {
                Log::info('Automation log processor starting', [
                    'timestamp' => now()->toIso8601String(),
                ]);
            })
            ->after(function () {
                Log::info('Automation log processor completed', [
                    'timestamp' => now()->toIso8601String(),
                ]);
            });

        // Daily automation summary (simplified)
        $schedule->call(function () {
            $totalAutomations = DB::table('automation_action_logs')
                ->whereDate('created_at', today())
                ->whereNull('deleted_at')
                ->count();

            if ($totalAutomations > 0) {
                Log::info('Daily Automation Summary', [
                    'date' => today()->toDateString(),
                    'total_automations' => $totalAutomations,
                ]);
            }
        })
            ->dailyAt('23:55');

        $schedule->command('fieldroutes:sync-employees')->dailyAt('01:15')
            ->when(function () {
                // Check if any integration with status=1 exists
                return DB::table('integrations')->where('name', 'FieldRoutes')->where('status', 1)->exists();
            })
            ->sentryMonitor(
                checkInMargin: 15,
                maxRuntime: 30,
                failureIssueThreshold: 1
            );
        //  $schedule->command('fieldroutes:import-subscriptions --batch=500 --memory-limit=4096 --parallel=8')->everyThirtyMinutes()->withoutOverlapping()
        //     ->when(function () {
        //         // Check if any integration with status=1 exists
        //         return DB::table('integrations')->where('name', 'FieldRoutes')->where('status', 1)->exists();
        //     })
        //     ->sentryMonitor(
        //         checkInMargin: 15,
        //         maxRuntime: 60,
        //         failureIssueThreshold: 1
        //     );
        $schedule->command('send:tax-documents')->dailyAt('00:30');

        $schedule->command('tier:sync')->dailyAt('00:30');
        $schedule->command('tiers:reset')->dailyAt('00:30');

        $schedule->command('payments:alert-pending-48-hours')->daily();

        // Schedule the legacy API data logging command to run every 2 hours
        // Also delete records from source table after logging (--delete) without confirmation (--force)
        $schedule->command('app:log-legacy-api-data --delete --force')
            ->everyTwoHours()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/legacy-api-logging.log'));
        // To create missing journal entries
        $schedule->command('retry:journal-entry-jobs')->everyFiveMinutes();

        // Clean up stuck jobs that have been processing for too long (run every 10 minutes)
        $schedule->command('jobs:cleanup-stuck --hours=1 --force')->everyTenMinutes();

        // Clean up old job performance logs daily to maintain database performance
        $schedule->command('queue:cleanup-performance-logs --days=30')->dailyAt('02:00');

        // Laravel 10: Prune stale cache tags (only needed for Redis cache driver)
        $schedule->command('cache:prune-stale-tags')
            ->hourly()
            ->when(function () {
                return config('cache.default') === 'redis';
            });

        // Horizon metrics snapshot (required for Horizon dashboard "Metrics" to show data)
        $schedule->command('horizon:snapshot')->everyFiveMinutes();

        // $schedule->command('import:home-team-dump-data-into-new-table')
        //     ->everyThirtyMinutes()
        //     ->withoutOverlapping()
        //     ->when(function () {
        //         $exists = DB::table('integrations')
        //             ->where('name', 'sftp-json')
        //             ->where('status', 1)
        //             ->exists();

        //         Log::info('Home Team scheduler condition result: '.($exists ? 'true' : 'false'));

        //         if (! $exists) {
        //             Log::info('Home Team scheduler skipping execution due to no matching integrations');
        //         }

        //         return $exists;
        //     });

        // $schedule->command('import:sftp-json --batch=500 --memory-limit=2048 --timeout=3600')
        //     ->everyThirtyMinutes()
        //     ->withoutOverlapping()
        //     ->when(function () {
        //         $exists = DB::table('integrations')->where('name', 'sftp-json')->where('status', 1)->exists();
        //         Log::info('SFTP-JSON scheduler condition result: '.($exists ? 'true' : 'false'));
        //         if (! $exists) {
        //             Log::info('SFTP-JSON scheduler skipping execution due to no matching integrations');
        //         }

        //         return $exists;
        //     })
        //     ->sentryMonitor(
        //         checkInMargin: 15,
        //         maxRuntime: 60,  // Adjusted to match command timeout of 3600 seconds (60 minutes)
        //         failureIssueThreshold: 1
        //     )
        //     ->before(function () {
        //         if (app()->bound('sentry')) {
        //             // Create transaction context with detailed attributes
        //             $context = \Sentry\Tracing\TransactionContext::make()
        //                 ->setName('scheduled.command.import:sftp-json')
        //                 ->setOp('import.sftp-json.scheduled')
        //                 ->setData([
        //                     'batch_size' => 500,
        //                     'memory_limit' => 2048,
        //                     'timeout' => 3600,
        //                     'schedule' => 'every_30_minutes',
        //                     'start_time' => now()->toIso8601String(),
        //                 ]);

        //             // Start the transaction and store it in the container
        //             $transaction = \Sentry\startTransaction($context);
        //             app()->instance('sftp.json.transaction', $transaction);

        //             // Set the transaction as the current transaction
        //             \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);
        //         }
        //     })
        //     ->after(function () {
        //         if (app()->bound('sentry') && app()->bound('sftp.json.transaction')) {
        //             /** @var \Sentry\Tracing\Transaction $transaction */
        //             $transaction = app()->make('sftp.json.transaction');

        //             // Add final execution data
        //             $transaction->setData(array_merge(
        //                 $transaction->getData() ?? [],
        //                 ['end_time' => now()->toIso8601String()]
        //             ));

        //             // Finish the transaction
        //             $transaction->finish();

        //             // Clear the current transaction
        //             \Sentry\SentrySdk::getCurrentHub()->setSpan(null);
        //         }
        //     });

        // $schedule->command('import:clark-dump-data-into-new-table')->everyThirtyMinutes()->withoutOverlapping()
        //     ->when(function () {
        //         $exists = DB::table('integrations')->where('name', 'clark-excel')->where('status', 1)->exists();
        //         Log::info('CLARK-EXCEL scheduler condition result: '.($exists ? 'true' : 'false'));
        //         if (! $exists) {
        //             Log::info('CLARK-EXCEL scheduler skipping execution due to no matching integrations');
        //         }

        //         return $exists;
        //     });

        // $schedule->command('import:clark-excel --batch=500 --memory-limit=2048 --timeout=3600')->hourly()
        //     ->withoutOverlapping()
        //     ->when(function () {
        //         $exists = DB::table('integrations')->where('name', 'clark-excel')->where('status', 1)->exists();
        //         Log::info('CLARK-EXCEL scheduler condition result: '.($exists ? 'true' : 'false'));
        //         if (! $exists) {
        //             Log::info('CLARK-EXCEL scheduler skipping execution due to no matching integrations');
        //         }

        //         return $exists;
        //     })
        //     ->sentryMonitor(
        //         checkInMargin: 15,
        //         maxRuntime: 3600,  // Set to match command timeout of 3600 seconds (60 minutes)
        //         failureIssueThreshold: 1
        //     )
        //     ->before(function () {
        //         if (app()->bound('sentry')) {
        //             // Create transaction context with detailed attributes
        //             $context = \Sentry\Tracing\TransactionContext::make()
        //                 ->setName('scheduled.command.import:clark-excel')
        //                 ->setOp('import.clark-excel.scheduled')
        //                 ->setData([
        //                     'batch_size' => 500,
        //                     'memory_limit' => 2048,
        //                     'timeout' => 3600,
        //                     'schedule' => 'hourly',
        //                     'start_time' => now()->toIso8601String(),
        //                 ]);

        //             // Start the transaction and store it in the container
        //             $transaction = \Sentry\startTransaction($context);
        //             app()->instance('clark.excel.transaction', $transaction);

        //             // Set the transaction as the current transaction
        //             \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);
        //         }
        //     })
        //     ->after(function () {
        //         try {
        //             if (app()->bound('sentry') && app()->bound('clark.excel.transaction')) {
        //                 /** @var \Sentry\Tracing\Transaction $transaction */
        //                 $transaction = app()->make('clark.excel.transaction');

        //                 // Get execution stats if available
        //                 $stats = [];
        //                 if (app()->bound('clark.excel.stats')) {
        //                     $stats = app()->make('clark.excel.stats');
        //                 }

        //                 // Add final execution data
        //                 $transaction->setData(array_merge(
        //                     $transaction->getData() ?? [],
        //                     [
        //                         'end_time' => now()->toIso8601String(),
        //                         'execution_stats' => $stats,
        //                         'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 .'MB',
        //                     ]
        //                 ));

        //                 // Set transaction status
        //                 if (app()->bound('clark.excel.error')) {
        //                     $transaction->setStatus(\Sentry\Tracing\SpanStatus::internalError());
        //                 } else {
        //                     $transaction->setStatus(\Sentry\Tracing\SpanStatus::ok());
        //                 }

        //                 // Finish the transaction
        //                 $transaction->finish();
        //             }
        //         } catch (\Throwable $e) {
        //             Log::error('Error in clark-excel transaction finish', [
        //                 'error' => $e->getMessage(),
        //                 'trace' => $e->getTraceAsString(),
        //             ]);
        //         } finally {
        //             // Always clear the current transaction
        //             \Sentry\SentrySdk::getCurrentHub()->setSpan(null);
        //         }
        //     });
        $schedule->command('ApplyHistoryOnUsersV2:update')->dailyAt('00:45');

        // NEW CONTRACT ACTIVATION: Activate new contracts starting today
        $schedule->command('new-contracts:activate')->dailyAt('01:00');

        // Schedule the Pocomos raw data sync command to run hourly
        // $schedule->command('pocomos:sync-raw-data --batch=500 --memory-limit=2048 --timeout=3600')
        //     ->hourly()
        //     ->withoutOverlapping()
        //     ->when(function () {
        //         // Check if any Pocomos integration with status=1 exists
        //         return DB::table('integrations')->where('name', 'Pocomos')->where('status', 1)->exists();
        //     })
        //     ->sentryMonitor(
        //         checkInMargin: 15,
        //         maxRuntime: 60,
        //         failureIssueThreshold: 1
        //     )
        //     ->appendOutputTo(storage_path('logs/pocomos-raw-data-sync.log'));

        // // RE CALCULATE PAYROLL DATA - Enhanced for reliability
        // $schedule->command('payroll:re-calculate --memory-limit=2048 --timeout=3600')
        //     ->everySixHours()
        //     ->withoutOverlapping(90) // Clear lock after 90 minutes if command crashes or gets stuck
        //     ->runInBackground() // Don't block other scheduled commands
        //     ->appendOutputTo(storage_path('logs/payroll-recalculate.log'))
        //     ->before(function () {
        //         Log::info('[Payroll Scheduler] Re-calculate starting', [
        //             'timestamp' => now()->toIso8601String(),
        //             'scheduled_time' => now()->format('Y-m-d H:i:s'),
        //         ]);
        //     })
        //     ->after(function () {
        //         Log::info('[Payroll Scheduler] Re-calculate completed', [
        //             'timestamp' => now()->toIso8601String(),
        //             'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 . 'MB',
        //         ]);
        //     })
        //     ->onFailure(function () {
        //         Log::error('[Payroll Scheduler] Re-calculate failed', [
        //             'timestamp' => now()->toIso8601String(),
        //         ]);
        //     });

       // FieldRoutes chained synchronization - runs every hour (get-subscriptions + sync-data)
        // COMMENTED OUT: Replaced with separate cron jobs for better control and logging

        // $fromDate = now()->subDay()->format('Y-m-d');
        // $toDate = now()->addDay()->format('Y-m-d');
        // $schedule->command("fieldroutes:chained-sync {$fromDate} {$toDate} --all --save")
        // ->hourly()
        // ->withoutOverlapping()
        // ->appendOutputTo(storage_path('logs/fieldroutes-chained.log'))
        // ->when(function () {
        //     return DB::table('integrations')->where('name', 'FieldRoutes')->where('status', 1)->exists();
        // });

        // FieldRoutes data synchronization - runs every 30 minutes
        $schedule->command('fieldroutes:sync-data')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/fieldroutes-sync.log'))
            ->when(function () {
                return DB::table('integrations')->where('name', 'FieldRoutes')->where('status', 1)->exists();
            })
            ->sentryMonitor(
                checkInMargin: 15,
                maxRuntime: 30,
                failureIssueThreshold: 1
            );

        // CALCULATES PAYROLL SALARY
        $schedule->command('payroll:salary-calculate --memory-limit=1024 --timeout=3600')
            ->dailyAt('00:55')
            ->withoutOverlapping(90) // Clear lock after 90 minutes if command crashes or gets stuck
            ->runInBackground() // Don't block other scheduled commands
            ->appendOutputTo(storage_path('logs/payroll-salary-calculate.log'))
            ->before(function () {
                Log::info('[Payroll Scheduler] Salary calculate starting.', [
                    'timestamp' => now()->toIso8601String(),
                    'scheduled_time' => now()->format('Y-m-d H:i:s'),
                ]);
            })->after(function () {
                Log::info('[Payroll Scheduler] Salary calculate completed.', [
                    'timestamp' => now()->toIso8601String(),
                    'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 . 'MB',
                ]);
            })->onFailure(function () {
                Log::error('[Payroll Scheduler] Salary calculate failed.', [
                    'timestamp' => now()->toIso8601String(),
                ]);
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
