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
     * Update product_id and product_code in user_overrides and user_overrides_lock tables
     * from product_codes table based on matching product_id, and sync from base table to lock table
     */
    public function up(): void
    {
        echo "🔄 Starting product_code and product_id update for user_overrides tables...\n\n";

        // ========== STEP 1: Update user_overrides from product_codes ==========
        if (Schema::hasTable('user_overrides') && Schema::hasTable('product_codes')) {
            echo "📊 STEP 1: Updating user_overrides table from product_codes...\n";

            $totalRecords = DB::table('user_overrides')->count();
            echo "  Total records in user_overrides: {$totalRecords}\n";

            // Get mismatch stats before
            $beforeStats = DB::select("
                SELECT 
                    COUNT(*) as total_with_product,
                    SUM(CASE WHEN uo.product_code IS NULL OR uo.product_code = '' THEN 1 ELSE 0 END) as null_product_code,
                    SUM(CASE WHEN uo.product_id IS NULL THEN 1 ELSE 0 END) as null_product_id
                FROM user_overrides uo
                WHERE uo.product_id IS NOT NULL
            ");
            $before = $beforeStats[0];
            echo "  Records with product_id: {$before->total_with_product}\n";
            echo "  NULL/empty product_code: {$before->null_product_code}\n";
            echo "  NULL product_id: {$before->null_product_id}\n";

            // Update from product_codes table
            // Both use utf8mb4_unicode_ci but explicit collation for safety
            DB::statement("
                UPDATE user_overrides uo
                INNER JOIN product_codes pc ON uo.product_id = pc.product_id
                SET uo.product_code = pc.product_code
                WHERE uo.product_code IS NULL 
                OR uo.product_code = ''
                OR uo.product_code COLLATE utf8mb4_general_ci != pc.product_code COLLATE utf8mb4_general_ci
            ");

            echo "  ✅ Updated user_overrides from product_codes\n\n";
        }

        // ========== STEP 2: Update user_overrides_lock from product_codes ==========
        if (Schema::hasTable('user_overrides_lock') && Schema::hasTable('product_codes')) {
            echo "📊 STEP 2: Updating user_overrides_lock table from product_codes...\n";

            $lockTotalRecords = DB::table('user_overrides_lock')->count();
            echo "  Total records in user_overrides_lock: {$lockTotalRecords}\n";

            $lockBeforeStats = DB::select("
                SELECT 
                    COUNT(*) as total_with_product,
                    SUM(CASE WHEN uol.product_code IS NULL OR uol.product_code = '' THEN 1 ELSE 0 END) as null_product_code,
                    SUM(CASE WHEN uol.product_id IS NULL THEN 1 ELSE 0 END) as null_product_id
                FROM user_overrides_lock uol
                WHERE uol.product_id IS NOT NULL
            ");
            $lockBefore = $lockBeforeStats[0];
            echo "  Records with product_id: {$lockBefore->total_with_product}\n";
            echo "  NULL/empty product_code: {$lockBefore->null_product_code}\n";
            echo "  NULL product_id: {$lockBefore->null_product_id}\n";

            // user_overrides_lock uses utf8mb4_0900_ai_ci, product_codes uses utf8mb4_unicode_ci
            DB::statement("
                UPDATE user_overrides_lock uol
                INNER JOIN product_codes pc ON uol.product_id = pc.product_id
                SET uol.product_code = pc.product_code
                WHERE uol.product_code IS NULL 
                OR uol.product_code = ''
                OR uol.product_code COLLATE utf8mb4_general_ci != pc.product_code COLLATE utf8mb4_general_ci
            ");

            echo "  ✅ Updated user_overrides_lock from product_codes\n\n";
        }

        // ========== STEP 3: Sync user_overrides_lock from user_overrides ==========
        if (Schema::hasTable('user_overrides') && Schema::hasTable('user_overrides_lock')) {
            echo "📊 STEP 3: Syncing user_overrides_lock from user_overrides...\n";

            // Statistics query also needs collation handling
            $syncStats = DB::select("
                SELECT 
                    COUNT(*) as total_with_source,
                    SUM(CASE WHEN uol.product_id IS NULL OR uol.product_id != uo.product_id THEN 1 ELSE 0 END) as product_id_mismatch,
                    SUM(CASE WHEN uol.product_code IS NULL OR uol.product_code = '' 
                        OR uol.product_code COLLATE utf8mb4_general_ci != uo.product_code COLLATE utf8mb4_general_ci THEN 1 ELSE 0 END) as product_code_mismatch
                FROM user_overrides_lock uol
                INNER JOIN user_overrides uo ON uol.id = uo.id
            ");

            if (count($syncStats) > 0) {
                $sync = $syncStats[0];
                echo "  Records to sync: {$sync->total_with_source}\n";
                echo "  product_id mismatches: {$sync->product_id_mismatch}\n";
                echo "  product_code mismatches: {$sync->product_code_mismatch}\n";

                // user_overrides (utf8mb4_unicode_ci) vs user_overrides_lock (utf8mb4_0900_ai_ci)
                DB::statement("
                    UPDATE user_overrides_lock uol
                    INNER JOIN user_overrides uo ON uol.id = uo.id
                    SET 
                        uol.product_id = uo.product_id,
                        uol.product_code = uo.product_code
                    WHERE 
                        uol.product_id IS NULL 
                        OR uol.product_id != uo.product_id
                        OR uol.product_code IS NULL 
                        OR uol.product_code = ''
                        OR uol.product_code COLLATE utf8mb4_general_ci != uo.product_code COLLATE utf8mb4_general_ci
                ");

                echo "  ✅ Synced user_overrides_lock from user_overrides\n\n";
            }
        }

        // ========== FINAL VERIFICATION ==========
        echo "📊 FINAL VERIFICATION:\n";

        if (Schema::hasTable('user_overrides')) {
            $finalStats = DB::select("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN product_code IS NULL OR product_code = '' THEN 1 ELSE 0 END) as null_code,
                    SUM(CASE WHEN product_id IS NULL THEN 1 ELSE 0 END) as null_id
                FROM user_overrides
            ");
            $final = $finalStats[0];
            echo "  user_overrides: {$final->total} total, {$final->null_code} NULL codes, {$final->null_id} NULL ids\n";
        }

        if (Schema::hasTable('user_overrides_lock')) {
            $finalLockStats = DB::select("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN product_code IS NULL OR product_code = '' THEN 1 ELSE 0 END) as null_code,
                    SUM(CASE WHEN product_id IS NULL THEN 1 ELSE 0 END) as null_id
                FROM user_overrides_lock
            ");
            $finalLock = $finalLockStats[0];
            echo "  user_overrides_lock: {$finalLock->total} total, {$finalLock->null_code} NULL codes, {$finalLock->null_id} NULL ids\n";
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
