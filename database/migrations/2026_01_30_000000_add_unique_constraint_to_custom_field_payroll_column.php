<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add unique constraint on custom_field (payroll_id, column_id) to prevent duplicate
 * custom field rows per payroll/column (RCA: custom_payment wrong deduction).
 *
 * Removes duplicate rows first by keeping the row with the minimum id per
 * (payroll_id, column_id), then adds the unique index.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('custom_field')) {
            return;
        }

        $indexName = 'custom_field_payroll_id_column_id_unique';

        if ($this->indexExists('custom_field', $indexName)) {
            return;
        }

        // Note: Cannot use DB::transaction() here because MySQL DDL statements
        // (CREATE INDEX) cause an implicit commit and break transactions.
        
        // Backup duplicates before deletion for recovery if needed
        // Creates backup table only if there are duplicates to backup
        $duplicateCount = DB::selectOne('
            SELECT COUNT(*) as cnt FROM custom_field cf1
            INNER JOIN custom_field cf2
                ON cf1.payroll_id = cf2.payroll_id
                AND cf1.column_id = cf2.column_id
                AND cf1.id < cf2.id
        ')->cnt ?? 0;
        
        if ($duplicateCount > 0) {
            // Create backup table with timestamp for easy identification
            $backupTable = 'custom_field_duplicates_backup_' . date('Y_m_d_His');
            
            DB::statement("
                CREATE TABLE IF NOT EXISTS `{$backupTable}` AS
                SELECT cf2.* FROM custom_field cf1
                INNER JOIN custom_field cf2
                    ON cf1.payroll_id = cf2.payroll_id
                    AND cf1.column_id = cf2.column_id
                    AND cf1.id < cf2.id
            ");
            
            \Illuminate\Support\Facades\Log::info(
                "[Migration] Backed up {$duplicateCount} duplicate custom_field rows to table: {$backupTable}"
            );
        }
        
        // Remove duplicates: keep the row with the smallest id per (payroll_id, column_id)
        DB::statement('
            DELETE cf2 FROM custom_field cf1
            INNER JOIN custom_field cf2
                ON cf1.payroll_id = cf2.payroll_id
                AND cf1.column_id = cf2.column_id
                AND cf1.id < cf2.id
        ');

        Schema::table('custom_field', function (Blueprint $table) use ($indexName) {
            $table->unique(['payroll_id', 'column_id'], $indexName);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('custom_field')) {
            return;
        }

        $indexName = 'custom_field_payroll_id_column_id_unique';

        if ($this->indexExists('custom_field', $indexName)) {
            Schema::table('custom_field', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $tableName = DB::getTablePrefix() . $table;
        $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);

        return !empty($indexes);
    }
};
