<?php

namespace App\Console\Commands;

use App\Models\FrEmployeeData;
use App\Models\Integration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessFieldRoutesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fieldroutes:process-data 
                            {action : Action to perform (stats|list-integrations|list-employees|sync-status|employees-by-office)}
                            {--office= : Filter by specific office name}
                            {--type= : Filter by employee type (0=Office Staff, 1=Technician, 2=Sales Rep)}
                            {--active : Only show active records}
                            {--with-sequifi : Only show employees with sequifi_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and analyze FieldRoutes integrations and employee data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        match ($action) {
            'stats' => $this->showStats(),
            'list-integrations' => $this->listIntegrations(),
            'list-employees' => $this->listEmployees(),
            'sync-status' => $this->showSyncStatus(),
            'employees-by-office' => $this->showEmployeesByOffice(),
            default => $this->error("Unknown action: {$action}. Available actions: stats, list-integrations, list-employees, sync-status, employees-by-office")
        };

        return 0;
    }

    /**
     * Display comprehensive statistics about integrations and employees
     */
    protected function showStats()
    {
        $this->info('📊 FieldRoutes Data Statistics');
        $this->line('');

        // Integration stats
        $totalIntegrations = Integration::count();
        $activeIntegrations = Integration::active()->count();
        $fieldRoutesIntegrations = Integration::fieldRoutes()->count();

        $this->line('🔗 <comment>Integration Statistics:</comment>');
        $this->line("   Total Integrations: <info>{$totalIntegrations}</info>");
        $this->line("   Active Integrations: <info>{$activeIntegrations}</info>");
        $this->line("   FieldRoutes Integrations: <info>{$fieldRoutesIntegrations}</info>");
        $this->line('');

        // Employee stats
        $totalEmployees = FrEmployeeData::count();
        $activeEmployees = FrEmployeeData::active()->count();
        $employeesWithSequifi = FrEmployeeData::active()->withSequifiId()->count();

        $this->line('👥 <comment>Employee Statistics:</comment>');
        $this->line("   Total Employees: <info>{$totalEmployees}</info>");
        $this->line("   Active Employees: <info>{$activeEmployees}</info>");
        $this->line("   Active with Sequifi ID: <info>{$employeesWithSequifi}</info>");
        $this->line('');

        // Employee types breakdown
        $salesReps = FrEmployeeData::active()->salesReps()->count();
        $technicians = FrEmployeeData::active()->technicians()->count();
        $officeStaff = FrEmployeeData::active()->officeStaff()->count();

        $this->line('📋 <comment>Employee Types (Active):</comment>');
        $this->line("   Sales Reps: <info>{$salesReps}</info>");
        $this->line("   Technicians: <info>{$technicians}</info>");
        $this->line("   Office Staff: <info>{$officeStaff}</info>");
        $this->line('');

        // Office distribution
        $officeStats = FrEmployeeData::active()
            ->select('office_name', DB::raw('count(*) as employee_count'))
            ->groupBy('office_name')
            ->orderBy('employee_count', 'desc')
            ->limit(10)
            ->get();

        $this->line('🏢 <comment>Top 10 Offices by Employee Count:</comment>');
        foreach ($officeStats as $office) {
            $this->line("   {$office->office_name}: <info>{$office->employee_count}</info> employees");
        }
    }

    /**
     * List all integrations with their details
     */
    protected function listIntegrations()
    {
        $this->info('🔗 FieldRoutes Integrations');
        $this->line('');

        $query = Integration::query();

        if ($this->option('active')) {
            $query->active();
        }

        $integrations = $query->get();

        if ($integrations->isEmpty()) {
            $this->warn('No integrations found.');

            return;
        }

        $headers = ['ID', 'Name', 'Description', 'Status', 'Created', 'Employees'];
        $rows = [];

        foreach ($integrations as $integration) {
            $employeeCount = FrEmployeeData::where('office_name', $integration->description)
                ->active()
                ->count();

            $rows[] = [
                $integration->id,
                $integration->name,
                $integration->description,
                $integration->status ? '✅ Active' : '❌ Inactive',
                $integration->created_at->format('Y-m-d'),
                $employeeCount,
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * List employees with filtering options
     */
    protected function listEmployees()
    {
        $this->info('👥 FieldRoutes Employees');
        $this->line('');

        $query = FrEmployeeData::query();

        // Apply filters
        if ($this->option('active')) {
            $query->active();
        }

        if ($this->option('with-sequifi')) {
            $query->withSequifiId();
        }

        if ($office = $this->option('office')) {
            $query->where('office_name', 'like', "%{$office}%");
        }

        if ($type = $this->option('type')) {
            $query->byType($type);
        }

        $employees = $query->orderBy('office_name')->orderBy('fname')->limit(50)->get();

        if ($employees->isEmpty()) {
            $this->warn('No employees found with the specified criteria.');

            return;
        }

        $headers = ['ID', 'Employee ID', 'Name', 'Type', 'Office', 'Sequifi ID', 'Status'];
        $rows = [];

        foreach ($employees as $employee) {
            $rows[] = [
                $employee->id,
                $employee->employee_id,
                $employee->full_name ?: 'N/A',
                $employee->type_name,
                $employee->office_name,
                $employee->sequifi_id ?: 'N/A',
                $employee->active ? '✅ Active' : '❌ Inactive',
            ];
        }

        $this->table($headers, $rows);

        if ($employees->count() == 50) {
            $this->line('');
            $this->comment('Showing first 50 results. Use filters to narrow down the results.');
        }
    }

    /**
     * Show sync readiness status
     */
    protected function showSyncStatus()
    {
        $this->info('🔄 Sync Readiness Status');
        $this->line('');

        // Check integrations ready for sync
        $activeIntegrations = Integration::active()->fieldRoutes()->get();

        $this->line("<comment>Active FieldRoutes Integrations:</comment> <info>{$activeIntegrations->count()}</info>");
        $this->line('');

        foreach ($activeIntegrations as $integration) {
            $employeeCount = FrEmployeeData::where('office_name', $integration->description)
                ->active()
                ->withSequifiId()
                ->count();

            $status = $employeeCount > 0 ? '✅ Ready' : '⚠️  No eligible employees';
            $this->line("  📍 {$integration->description}: <info>{$employeeCount}</info> employees {$status}");
        }

        $this->line('');

        // Summary for sync job
        $totalEligibleEmployees = FrEmployeeData::active()->withSequifiId()->count();
        $totalOffices = FrEmployeeData::active()->withSequifiId()
            ->distinct('office_name')
            ->count('office_name');

        $this->line('📊 <comment>Sync Summary:</comment>');
        $this->line("   Total eligible employees: <info>{$totalEligibleEmployees}</info>");
        $this->line("   Offices with eligible employees: <info>{$totalOffices}</info>");

        if ($totalEligibleEmployees > 0) {
            $this->line('');
            $this->info('✅ System is ready for subscription sync!');
        } else {
            $this->line('');
            $this->error('❌ No employees eligible for sync. Check sequifi_id assignments.');
        }
    }

    /**
     * Show employees grouped by office
     */
    protected function showEmployeesByOffice()
    {
        $this->info('🏢 Employees by Office');
        $this->line('');

        $query = FrEmployeeData::query();

        if ($this->option('active')) {
            $query->active();
        }

        if ($this->option('with-sequifi')) {
            $query->withSequifiId();
        }

        if ($type = $this->option('type')) {
            $query->byType($type);
        }

        $offices = $query->select('office_name', DB::raw('count(*) as employee_count'))
            ->groupBy('office_name')
            ->orderBy('employee_count', 'desc')
            ->get();

        if ($offices->isEmpty()) {
            $this->warn('No offices found with the specified criteria.');

            return;
        }

        $headers = ['Office Name', 'Employee Count', 'Has Integration'];
        $rows = [];

        foreach ($offices as $office) {
            $hasIntegration = Integration::where('description', $office->office_name)
                ->active()
                ->exists();

            $rows[] = [
                $office->office_name,
                $office->employee_count,
                $hasIntegration ? '✅ Yes' : '❌ No',
            ];
        }

        $this->table($headers, $rows);
    }
}
