<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeSalesExportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:optimize-export {--analyze : Analyze current performance} {--apply : Apply optimizations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize sales export performance by analyzing and applying database optimizations';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('analyze')) {
            $this->analyzePerformance();
        }

        if ($this->option('apply')) {
            $this->applyOptimizations();
        }

        if (! $this->option('analyze') && ! $this->option('apply')) {
            $this->info('Use --analyze to check performance or --apply to implement optimizations');
        }
    }

    /**
     * Analyze current export performance
     */
    private function analyzePerformance()
    {
        $this->info('🔍 Analyzing Sales Export Performance...');
        $this->newLine();

        // Check table sizes
        $this->checkTableSizes();

        // Check existing indexes
        $this->checkExistingIndexes();

        // Analyze slow queries
        $this->analyzeSlowQueries();

        // Check for missing indexes
        $this->checkMissingIndexes();
    }

    /**
     * Apply performance optimizations
     */
    private function applyOptimizations()
    {
        $this->info('⚡ Applying Sales Export Optimizations...');
        $this->newLine();

        // Run the migration
        $this->call('migrate', ['--path' => 'database/migrations/2024_01_02_000000_add_sales_export_performance_indexes.php']);

        // Analyze tables for optimization
        $this->optimizeTables();

        // Create materialized views if needed
        $this->createMaterializedViews();

        $this->info('✅ Optimizations applied successfully!');
    }

    /**
     * Check table sizes for export-related tables
     */
    private function checkTableSizes()
    {
        $this->info('📊 Table Sizes:');

        $tables = ['sale_masters', 'user_commissions', 'sale_product_masters', 'clawback_settlements', 'sale_master_process'];

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            $size = $this->getTableSize($table);
            $this->line("  {$table}: {$count} rows ({$size})");
        }
        $this->newLine();
    }

    /**
     * Check existing indexes
     */
    private function checkExistingIndexes()
    {
        $this->info('🔑 Existing Indexes:');

        $indexes = DB::select("
            SELECT 
                TABLE_NAME,
                INDEX_NAME,
                GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME IN ('sale_masters', 'user_commissions', 'sale_product_masters')
            AND INDEX_NAME != 'PRIMARY'
            GROUP BY TABLE_NAME, INDEX_NAME
            ORDER BY TABLE_NAME, INDEX_NAME
        ");

        foreach ($indexes as $index) {
            $this->line("  {$index->TABLE_NAME}.{$index->INDEX_NAME}: ({$index->COLUMNS})");
        }
        $this->newLine();
    }

    /**
     * Analyze slow queries related to exports
     */
    private function analyzeSlowQueries()
    {
        $this->info('🐌 Checking for potential slow query patterns:');

        // Check for queries without proper date indexes
        $salesWithoutDateIndex = DB::select("
            EXPLAIN SELECT COUNT(*) 
            FROM sale_masters 
            WHERE customer_signoff BETWEEN '2024-01-01' AND '2024-12-31'
        ");

        if (isset($salesWithoutDateIndex[0]->type) && $salesWithoutDateIndex[0]->type === 'ALL') {
            $this->warn('  ⚠️  customer_signoff queries doing full table scan');
        } else {
            $this->info('  ✅ customer_signoff queries using index');
        }

        $this->newLine();
    }

    /**
     * Check for missing critical indexes
     */
    private function checkMissingIndexes()
    {
        $this->info('🔍 Checking for missing critical indexes:');

        $criticalIndexes = [
            'sale_masters' => ['customer_signoff', 'pid', 'product_id'],
            'user_commissions' => ['pid', 'status', 'settlement_type'],
            'sale_product_masters' => ['pid', 'type', 'milestone_date'],
            'clawback_settlements' => ['pid'],
        ];

        foreach ($criticalIndexes as $table => $columns) {
            $existingIndexes = DB::select('
                SELECT DISTINCT INDEX_NAME, COLUMN_NAME
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ?
            ', [$table]);

            $existingColumns = collect($existingIndexes)->pluck('COLUMN_NAME')->toArray();

            foreach ($columns as $column) {
                if (! in_array($column, $existingColumns)) {
                    $this->warn("  ⚠️  Missing index on {$table}.{$column}");
                } else {
                    $this->info("  ✅ Index exists on {$table}.{$column}");
                }
            }
        }
        $this->newLine();
    }

    /**
     * Optimize tables after index creation
     */
    private function optimizeTables()
    {
        $this->info('🔧 Optimizing tables...');

        $tables = ['sale_masters', 'user_commissions', 'sale_product_masters', 'clawback_settlements'];

        foreach ($tables as $table) {
            DB::statement("OPTIMIZE TABLE {$table}");
            $this->line("  ✅ Optimized {$table}");
        }
        $this->newLine();
    }

    /**
     * Create materialized views for complex export queries
     */
    private function createMaterializedViews()
    {
        $this->info('📋 Creating optimized views...');

        // Create a view for export summary data
        DB::statement('
            CREATE OR REPLACE VIEW sales_export_summary AS
            SELECT 
                sm.pid,
                sm.customer_name,
                sm.customer_signoff,
                sm.product_id,
                sm.customer_state,
                sm.total_commission,
                sm.total_override,
                sm.kw,
                sm.epc,
                sm.net_epc,
                sm.date_cancelled,
                smp.closer1_id,
                smp.closer2_id,
                smp.setter1_id,
                smp.setter2_id,
                GROUP_CONCAT(DISTINCT cs.pid) as is_clawback
            FROM sale_masters sm
            LEFT JOIN sale_master_process smp ON sm.pid = smp.pid
            LEFT JOIN clawback_settlements cs ON sm.pid = cs.pid
            GROUP BY sm.pid
        ');

        $this->info('  ✅ Created sales_export_summary view');
        $this->newLine();
    }

    /**
     * Get table size in MB
     */
    private function getTableSize($table)
    {
        $result = DB::select('
            SELECT 
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE() 
            AND table_name = ?
        ', [$table]);

        return isset($result[0]) ? $result[0]->size_mb.' MB' : 'Unknown';
    }
}
