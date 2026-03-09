<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add unique constraints to pay frequency tables to prevent duplicate periods.
 * 
 * This migration adds database-level unique constraints to ensure that:
 * 1. No duplicate pay periods can be created
 * 2. Race conditions are prevented at database level
 * 3. Data integrity is enforced across all application paths
 * 
 * SAFETY: This migration checks for existing duplicates before adding constraints.
 * If duplicates exist, it logs them and skips the constraint for that table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add unique constraint to weekly_pay_frequencies
        $this->addUniqueConstraintSafely(
            'weekly_pay_frequencies',
            ['pay_period_from', 'pay_period_to'],
            'unique_weekly_period'
        );
        
        // Add unique constraint to monthly_pay_frequencies
        $this->addUniqueConstraintSafely(
            'monthly_pay_frequencies',
            ['pay_period_from', 'pay_period_to'],
            'unique_monthly_period'
        );
        
        // Add unique constraint to additional_pay_frequencies (includes type for bi-weekly/semi-monthly)
        $this->addUniqueConstraintSafely(
            'additional_pay_frequencies',
            ['pay_period_from', 'pay_period_to', 'type'],
            'unique_additional_period'
        );
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_pay_frequencies', function (Blueprint $table) {
            $table->dropUnique('unique_weekly_period');
        });
        
        Schema::table('monthly_pay_frequencies', function (Blueprint $table) {
            $table->dropUnique('unique_monthly_period');
        });
        
        Schema::table('additional_pay_frequencies', function (Blueprint $table) {
            $table->dropUnique('unique_additional_period');
        });
    }
    
    /**
     * Safely add unique constraint, checking for existing duplicates first.
     * 
     * @param string $table Table name
     * @param array $columns Columns to include in unique constraint
     * @param string $indexName Name of the unique index
     * @return void
     */
    private function addUniqueConstraintSafely(string $table, array $columns, string $indexName): void
    {
        // Check if table exists
        if (!Schema::hasTable($table)) {
            \Log::warning("[Migration] Table does not exist, skipping unique constraint", [
                'table' => $table,
                'index' => $indexName
            ]);
            return;
        }
        
        // Build column list for GROUP BY query
        $columnList = implode(', ', $columns);
        
        // Check for existing duplicates
        $duplicates = DB::table($table)
            ->select(DB::raw("{$columnList}, COUNT(*) as count"))
            ->groupBy($columns)
            ->having('count', '>', 1)
            ->get();
        
        if ($duplicates->isNotEmpty()) {
            \Log::error("[Migration] Cannot add unique constraint - duplicate records exist", [
                'table' => $table,
                'index' => $indexName,
                'duplicate_count' => $duplicates->count(),
                'duplicates' => $duplicates->toArray(),
                'recommendation' => "Manually resolve duplicate records in {$table} table before running this migration"
            ]);
            
            // Skip adding constraint but don't fail migration
            // This allows deployment to proceed while giving visibility to the issue
            return;
        }
        
        // No duplicates found - safe to add constraint
        Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
            $table->unique($columns, $indexName);
        });
        
        \Log::info("[Migration] Successfully added unique constraint", [
            'table' => $table,
            'index' => $indexName,
            'columns' => $columns
        ]);
    }
};
