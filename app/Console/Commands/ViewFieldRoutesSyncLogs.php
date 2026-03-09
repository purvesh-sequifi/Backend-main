<?php

namespace App\Console\Commands;

use App\Models\FieldRoutesSyncLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ViewFieldRoutesSyncLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fieldroutes:view-sync-logs 
                          {--office= : Filter by office name}
                          {--days=7 : Show logs from the last N days}
                          {--from= : Start date (Y-m-d format)}
                          {--to= : End date (Y-m-d format)}
                          {--limit=20 : Limit number of results}
                          {--summary : Show summary statistics only}
                          {--errors-only : Show only entries with errors}
                          {--detailed : Show detailed breakdown}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View and analyze FieldRoutes sync logs with filtering options';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('📊 FieldRoutes Sync Logs Analysis');
        $this->line('=====================================');
        $this->line('');

        // Build query based on options
        $query = FieldRoutesSyncLog::query();

        // Apply filters
        $this->applyFilters($query);

        // Get total count before applying limit
        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->warn('No sync logs found matching your criteria.');

            return;
        }

        $this->info("Found {$totalCount} sync log entries");
        $this->line('');

        // Show summary if requested
        if ($this->option('summary')) {
            $this->showSummary($query);

            return;
        }

        // Apply limit and get results
        $logs = $query->orderBy('execution_timestamp', 'desc')
            ->limit($this->option('limit'))
            ->get();

        // Display results
        if ($this->option('detailed')) {
            $this->showDetailedResults($logs);
        } else {
            $this->showTableResults($logs);
        }

        if ($totalCount > $this->option('limit')) {
            $this->line('');
            $this->comment("Showing {$this->option('limit')} of {$totalCount} results. Use --limit to see more.");
        }
    }

    /**
     * Apply filters to the query
     */
    protected function applyFilters($query)
    {
        // Office filter
        if ($office = $this->option('office')) {
            $query->forOffice($office);
        }

        // Date range filter
        if ($from = $this->option('from')) {
            $query->where('execution_timestamp', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $this->option('to')) {
            $query->where('execution_timestamp', '<=', Carbon::parse($to)->endOfDay());
        }

        // Default to last N days if no specific date range
        if (! $this->option('from') && ! $this->option('to')) {
            $days = $this->option('days');
            $query->where('execution_timestamp', '>=', Carbon::now()->subDays($days));
        }

        // Errors only filter
        if ($this->option('errors-only')) {
            $query->where('errors', '>', 0);
        }
    }

    /**
     * Show summary statistics
     */
    protected function showSummary($query)
    {
        $logs = $query->get();

        $totalRuns = $logs->count();
        $totalOffices = $logs->pluck('office_name')->unique()->count();
        $totalErrors = $logs->sum('errors');
        $totalApiInconsistencies = $logs->sum('records_not_fetched');
        $avgDuration = $logs->avg('duration_seconds');

        // Aggregate stats
        $totalSubscriptionsCreated = $logs->sum('subscriptions_created');
        $totalSubscriptionsUpdated = $logs->sum('subscriptions_updated');
        $totalCustomersCreated = $logs->sum('customers_created');
        $totalCustomersUpdated = $logs->sum('customers_updated');
        $totalAppointmentsCreated = $logs->sum('appointments_created');
        $totalAppointmentsUpdated = $logs->sum('appointments_updated');

        // Build summary table
        $summaryTable = [
            ['Total Sync Runs', number_format($totalRuns)],
            ['Unique Offices', number_format($totalOffices)],
            ['Total Errors', number_format($totalErrors)],
        ];

        // Only show API inconsistencies if there are any
        if ($totalApiInconsistencies > 0) {
            $summaryTable[] = ['API Inconsistencies', number_format($totalApiInconsistencies)];
        }

        $summaryTable = array_merge($summaryTable, [
            ['Average Duration (seconds)', number_format($avgDuration, 2)],
            [''],
            ['Subscriptions Created', number_format($totalSubscriptionsCreated)],
            ['Subscriptions Updated', number_format($totalSubscriptionsUpdated)],
            ['Customers Created', number_format($totalCustomersCreated)],
            ['Customers Updated', number_format($totalCustomersUpdated)],
            ['Appointments Created', number_format($totalAppointmentsCreated)],
            ['Appointments Updated', number_format($totalAppointmentsUpdated)],
        ]);

        $this->table(['Metric', 'Value'], $summaryTable);

        // Office performance
        $this->line('');
        $this->info('📈 Top Performing Offices (by total records processed):');
        $officeStats = $logs->groupBy('office_name')->map(function ($officeLogs) {
            return [
                'runs' => $officeLogs->count(),
                'total_processed' => $officeLogs->sum(function ($log) {
                    return $log->subscriptions_created + $log->subscriptions_updated +
                           $log->customers_created + $log->customers_updated +
                           $log->appointments_created + $log->appointments_updated;
                }),
                'errors' => $officeLogs->sum('errors'),
                'avg_duration' => $officeLogs->avg('duration_seconds'),
            ];
        })->sortByDesc('total_processed')->take(10);

        $officeTable = [];
        foreach ($officeStats as $office => $stats) {
            $officeTable[] = [
                $office,
                number_format($stats['runs']),
                number_format($stats['total_processed']),
                number_format($stats['errors']),
                number_format($stats['avg_duration'], 1).'s',
            ];
        }

        $this->table(['Office', 'Runs', 'Total Processed', 'Errors', 'Avg Duration'], $officeTable);
    }

    /**
     * Show results in table format
     */
    protected function showTableResults($logs)
    {
        $headers = [
            'Date/Time',
            'Office',
            'Duration',
            'Subs',
            'Customers',
            'Appointments',
            'Errors',
        ];

        $rows = [];
        foreach ($logs as $log) {
            $subsStats = ($log->subscriptions_created ? "C:{$log->subscriptions_created}" : '').
                        ($log->subscriptions_updated ? " U:{$log->subscriptions_updated}" : '');

            $customerStats = ($log->customers_created ? "C:{$log->customers_created}" : '').
                           ($log->customers_updated ? " U:{$log->customers_updated}" : '');

            $appointmentStats = ($log->appointments_created ? "C:{$log->appointments_created}" : '').
                              ($log->appointments_updated ? " U:{$log->appointments_updated}" : '');

            $rows[] = [
                $log->execution_timestamp->format('m/d H:i'),
                substr($log->office_name, 0, 20),
                $log->duration_seconds ? number_format($log->duration_seconds, 1).'s' : '-',
                $subsStats ?: '-',
                $customerStats ?: '-',
                $appointmentStats ?: '-',
                $log->errors ?: '-',
            ];
        }

        $this->table($headers, $rows);
        $this->line('');
        $this->comment('Legend: C=Created, U=Updated');
    }

    /**
     * Show detailed results
     */
    protected function showDetailedResults($logs)
    {
        foreach ($logs as $log) {
            // Determine command type from command_parameters or office_name
            $commandParams = json_decode($log->command_parameters, true);
            $commandType = $commandParams['command'] ?? 'fieldroutes:get-subscriptions';
            $isSyncData = $commandType === 'fieldroutes:sync-data' || $log->office_name === 'System-wide sync-data';

            if ($isSyncData) {
                // Sync-data specific display
                $this->line("🔄 <comment>Sync-Data Operation</comment> - {$log->execution_timestamp->format('Y-m-d H:i:s')}");
                $this->line('   🔄 Operation: System-wide data transformation');
                $this->line('   ⏱️  Duration: '.($log->duration_seconds ? number_format($log->duration_seconds, 2).'s' : 'Unknown'));
                $this->line('');

                $this->line('   📊 Data Processing:');
                $this->line("      Records Processed: <info>{$log->total_available}</info>");
                $this->line("      Legacy Records Saved: <info>{$log->subscriptions_updated}</info>");
                $this->line("      Records Touched: <info>{$log->records_touched}</info>");

                // Extract additional sync-data info from command_parameters
                if (isset($commandParams['last_run_timestamp'])) {
                    $this->line('      Last Run: <info>'.($commandParams['last_run_timestamp'] ?? 'Never').'</info>');
                }
                $this->line('');

                $this->line('   📈 Jobs & Processing:');
                $jobsDispatched = $log->subscriptions_updated > 0 ? 'SaleMasterJobs dispatched' : 'No jobs dispatched';
                $this->line("      Jobs Dispatched: <info>{$jobsDispatched}</info>");

            } else {
                // Get-subscriptions specific display (original logic)
                $this->line("🏢 <comment>{$log->office_name}</comment> - {$log->execution_timestamp->format('Y-m-d H:i:s')}");
                $this->line("   📅 Date Range: {$log->start_date} to {$log->end_date}");
                $this->line('   ⏱️  Duration: '.($log->duration_seconds ? number_format($log->duration_seconds, 2).'s' : 'Unknown'));
                $this->line("   👥 Reps Processed: {$log->reps_processed}");
                $this->line('');

                $this->line('   📊 Records Created:');
                $this->line("      Subscriptions: <info>{$log->subscriptions_created}</info>");
                $this->line("      Customers: <info>{$log->customers_created}</info>");
                $this->line("      Appointments: <info>{$log->appointments_created}</info>");
                $this->line('');

                $this->line('   🔄 Records Updated:');
                $this->line("      Subscriptions: <info>{$log->subscriptions_updated}</info>");
                $this->line("      Customers: <info>{$log->customers_updated}</info>");
                $this->line("      Appointments: <info>{$log->appointments_updated}</info>");
                $this->line('');

                if ($log->customers_updated > 0) {
                    $this->line('   👥 Customer Update Details:');
                    $this->line("      Personal Info: <info>{$log->customer_personal_changes}</info>");
                    $this->line("      Address: <info>{$log->customer_address_changes}</info>");
                    $this->line("      Status: <info>{$log->customer_status_changes}</info>");
                    $this->line("      Financial: <info>{$log->customer_financial_changes}</info>");
                    $this->line('');
                }

                if ($log->appointments_updated > 0) {
                    $this->line('   📅 Appointment Update Details:');
                    $this->line("      Status Changes: <info>{$log->appointment_status_changes}</info>");
                    $this->line("      Schedule Changes: <info>{$log->appointment_schedule_changes}</info>");
                    $this->line("      Identifier Changes: <info>{$log->appointment_identifier_changes}</info>");
                    $this->line('');
                }

                // Only show API inconsistencies when they occur
                if ($log->records_not_fetched > 0) {
                    $this->line("   ⚠️  API Inconsistency: <e>{$log->records_not_fetched}</e> records not fetched");
                    $this->line("      📊 Available: {$log->total_available}, Fetched: {$log->subscriptions_fetched}");
                    $this->line('');
                }
            }

            if ($log->errors > 0) {
                $this->line("   ❌ Errors: <e>{$log->errors}</e>");
                if ($log->error_details) {
                    $this->line("   📝 Error Details: {$log->error_details}");
                }
                $this->line('');
            }

            $this->line('---');
            $this->line('');
        }
    }
}
