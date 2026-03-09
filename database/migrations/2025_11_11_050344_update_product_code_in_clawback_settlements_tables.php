<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Update product_id and product_code in clawback_settlements and clawback_settlements_lock tables
     * from product_codes table based on matching product_id, and sync from base table to lock table
     */
    public function up(): void
    {
        echo "🔄 Starting product_code and product_id update for clawback_settlements tables...\n\n";

        // ========== STEP 1: Update clawback_settlements from product_codes ==========
        if (Schema::hasTable('clawback_settlements') && Schema::hasTable('product_codes')) {
            echo "📊 STEP 1: Updating clawback_settlements table from product_codes...\n";

            $totalRecords = DB::table('clawback_settlements')->count();
            echo "  Total records in clawback_settlements: {$totalRecords}\n";

            // Get mismatch stats before
            $beforeStats = DB::select("
                SELECT 
                    COUNT(*) as total_with_product,
                    SUM(CASE WHEN cs.product_code IS NULL OR cs.product_code = '' THEN 1 ELSE 0 END) as null_product_code,
                    SUM(CASE WHEN cs.product_id IS NULL THEN 1 ELSE 0 END) as null_product_id
                FROM clawback_settlements cs
                WHERE cs.product_id IS NOT NULL
            ");
            $before = $beforeStats[0];
            echo "  Records with product_id: {$before->total_with_product}\n";
            echo "  NULL/empty product_code: {$before->null_product_code}\n";
            echo "  NULL product_id: {$before->null_product_id}\n";

            // Update from product_codes table with collation handling
            DB::statement("
                UPDATE clawback_settlements cs
                INNER JOIN product_codes pc ON cs.product_id = pc.product_id
                SET cs.product_code = pc.product_code
                WHERE cs.product_code IS NULL 
                OR cs.product_code = ''
                OR cs.product_code COLLATE utf8mb4_general_ci != pc.product_code COLLATE utf8mb4_general_ci
            ");

            echo "  ✅ Updated clawback_settlements from product_codes\n\n";
        }

        // ========== STEP 2: Update clawback_settlements_lock from product_codes ==========
        if (Schema::hasTable('clawback_settlements_lock') && Schema::hasTable('product_codes')) {
            echo "📊 STEP 2: Updating clawback_settlements_lock table from product_codes...\n";

            $lockTotalRecords = DB::table('clawback_settlements_lock')->count();
            echo "  Total records in clawback_settlements_lock: {$lockTotalRecords}\n";

            $lockBeforeStats = DB::select("
                SELECT 
                    COUNT(*) as total_with_product,
                    SUM(CASE WHEN csl.product_code IS NULL OR csl.product_code = '' THEN 1 ELSE 0 END) as null_product_code,
                    SUM(CASE WHEN csl.product_id IS NULL THEN 1 ELSE 0 END) as null_product_id
                FROM clawback_settlements_lock csl
                WHERE csl.product_id IS NOT NULL
            ");
            $lockBefore = $lockBeforeStats[0];
            echo "  Records with product_id: {$lockBefore->total_with_product}\n";
            echo "  NULL/empty product_code: {$lockBefore->null_product_code}\n";
            echo "  NULL product_id: {$lockBefore->null_product_id}\n";

            // clawback_settlements_lock uses utf8mb4_0900_ai_ci, product_codes uses utf8mb4_unicode_ci
            DB::statement("
                UPDATE clawback_settlements_lock csl
                INNER JOIN product_codes pc ON csl.product_id = pc.product_id
                SET csl.product_code = pc.product_code
                WHERE csl.product_code IS NULL 
                OR csl.product_code = ''
                OR csl.product_code COLLATE utf8mb4_general_ci != pc.product_code COLLATE utf8mb4_general_ci
            ");

            echo "  ✅ Updated clawback_settlements_lock from product_codes\n\n";
        }

        // ========== STEP 3: Sync clawback_settlements_lock from clawback_settlements ==========
        if (Schema::hasTable('clawback_settlements') && Schema::hasTable('clawback_settlements_lock')) {
            echo "📊 STEP 3: Syncing clawback_settlements_lock from clawback_settlements...\n";

            // Statistics query with collation handling
            $syncStats = DB::select("
                SELECT 
                    COUNT(*) as total_with_source,
                    SUM(CASE WHEN csl.product_id IS NULL OR csl.product_id != cs.product_id THEN 1 ELSE 0 END) as product_id_mismatch,
                    SUM(CASE WHEN csl.product_code IS NULL OR csl.product_code = '' 
                        OR csl.product_code COLLATE utf8mb4_general_ci != cs.product_code COLLATE utf8mb4_general_ci THEN 1 ELSE 0 END) as product_code_mismatch
                FROM clawback_settlements_lock csl
                INNER JOIN clawback_settlements cs ON csl.id = cs.id
            ");

            if (count($syncStats) > 0) {
                $sync = $syncStats[0];
                echo "  Records to sync: {$sync->total_with_source}\n";
                echo "  product_id mismatches: {$sync->product_id_mismatch}\n";
                echo "  product_code mismatches: {$sync->product_code_mismatch}\n";

                // Sync with collation handling
                DB::statement("
                    UPDATE clawback_settlements_lock csl
                    INNER JOIN clawback_settlements cs ON csl.id = cs.id
                    SET 
                        csl.product_id = cs.product_id,
                        csl.product_code = cs.product_code
                    WHERE 
                        csl.product_id IS NULL 
                        OR csl.product_id != cs.product_id
                        OR csl.product_code IS NULL 
                        OR csl.product_code = ''
                        OR csl.product_code COLLATE utf8mb4_general_ci != cs.product_code COLLATE utf8mb4_general_ci
                ");

                echo "  ✅ Synced clawback_settlements_lock from clawback_settlements\n\n";
            }
        }

        // ========== FINAL VERIFICATION ==========
        echo "📊 FINAL VERIFICATION:\n";

        if (Schema::hasTable('clawback_settlements')) {
            $finalStats = DB::select("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN product_code IS NULL OR product_code = '' THEN 1 ELSE 0 END) as null_code,
                    SUM(CASE WHEN product_id IS NULL THEN 1 ELSE 0 END) as null_id
                FROM clawback_settlements
            ");
            $final = $finalStats[0];
            echo "  clawback_settlements: {$final->total} total, {$final->null_code} NULL codes, {$final->null_id} NULL ids\n";
        }

        if (Schema::hasTable('clawback_settlements_lock')) {
            $finalLockStats = DB::select("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN product_code IS NULL OR product_code = '' THEN 1 ELSE 0 END) as null_code,
                    SUM(CASE WHEN product_id IS NULL THEN 1 ELSE 0 END) as null_id
                FROM clawback_settlements_lock
            ");
            $finalLock = $finalLockStats[0];
            echo "  clawback_settlements_lock: {$finalLock->total} total, {$finalLock->null_code} NULL codes, {$finalLock->null_id} NULL ids\n";
        }

        echo "\n🎉 Migration completed successfully!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        echo "⚠️  This migration cannot be reversed as original values are unknown.\n";
        echo "⚠️  If you need to revert, restore from a database backup.\n";
    }
};
