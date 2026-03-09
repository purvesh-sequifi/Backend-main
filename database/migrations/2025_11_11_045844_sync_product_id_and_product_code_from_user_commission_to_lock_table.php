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
     * Sync product_id and product_code from user_commission to user_commission_lock
     * based on matching id
     */
    public function up(): void
    {
        // Check if both tables exist
        if (!Schema::hasTable('user_commission') || !Schema::hasTable('user_commission_lock')) {
            echo "⚠️  Required tables do not exist. Skipping migration.\n";
            return;
        }

        echo "🔄 Starting product_id and product_code sync from user_commission to user_commission_lock...\n\n";

        // Get statistics before update
        $lockTotalRecords = DB::table('user_commission_lock')->count();
        
        echo "📊 Statistics BEFORE sync:\n";
        echo "  Total records in lock table: {$lockTotalRecords}\n";

        // Check how many have mismatched or null values
        $mismatchedStats = DB::select("
            SELECT 
                COUNT(*) as total_with_source,
                SUM(CASE WHEN ucl.product_id IS NULL OR ucl.product_id != uc.product_id THEN 1 ELSE 0 END) as product_id_mismatch,
                SUM(CASE WHEN ucl.product_code IS NULL OR ucl.product_code = '' OR ucl.product_code != uc.product_code THEN 1 ELSE 0 END) as product_code_mismatch
            FROM user_commission_lock ucl
            INNER JOIN user_commission uc ON ucl.id = uc.id
        ");

        $stats = $mismatchedStats[0];
        echo "  Records with source data: {$stats->total_with_source}\n";
        echo "  product_id mismatches/nulls: {$stats->product_id_mismatch}\n";
        echo "  product_code mismatches/nulls: {$stats->product_code_mismatch}\n\n";

        // Update product_id and product_code in user_commission_lock from user_commission
        echo "🔄 Updating product_id and product_code...\n";
        
        $affectedRows = DB::statement("
            UPDATE user_commission_lock ucl
            INNER JOIN user_commission uc ON ucl.id = uc.id
            SET 
                ucl.product_id = uc.product_id,
                ucl.product_code = uc.product_code
            WHERE 
                ucl.product_id IS NULL 
                OR ucl.product_id != uc.product_id
                OR ucl.product_code IS NULL 
                OR ucl.product_code = ''
                OR ucl.product_code != uc.product_code
        ");

        echo "✅ Update query executed.\n\n";

        // Get statistics after update
        echo "📊 Statistics AFTER sync:\n";
        
        $afterStats = DB::select("
            SELECT 
                COUNT(*) as total_with_source,
                SUM(CASE WHEN ucl.product_id IS NULL OR ucl.product_id != uc.product_id THEN 1 ELSE 0 END) as product_id_mismatch,
                SUM(CASE WHEN ucl.product_code IS NULL OR ucl.product_code = '' OR ucl.product_code != uc.product_code THEN 1 ELSE 0 END) as product_code_mismatch
            FROM user_commission_lock ucl
            INNER JOIN user_commission uc ON ucl.id = uc.id
        ");

        $afterStatsData = $afterStats[0];
        echo "  Records with source data: {$afterStatsData->total_with_source}\n";
        echo "  product_id mismatches/nulls: {$afterStatsData->product_id_mismatch}\n";
        echo "  product_code mismatches/nulls: {$afterStatsData->product_code_mismatch}\n\n";

        // Summary
        $productIdFixed = $stats->product_id_mismatch - $afterStatsData->product_id_mismatch;
        $productCodeFixed = $stats->product_code_mismatch - $afterStatsData->product_code_mismatch;

        echo "✨ Records updated:\n";
        echo "  product_id synced: {$productIdFixed}\n";
        echo "  product_code synced: {$productCodeFixed}\n\n";

        if ($afterStatsData->product_id_mismatch == 0 && $afterStatsData->product_code_mismatch == 0) {
            echo "🎉 All records are now in sync!\n";
        } else {
            echo "⚠️  Some mismatches remain. These records might not have corresponding entries in user_commission table.\n";
        }

        echo "\n✅ Migration completed successfully!\n";
    }

    /**
     * Reverse the migrations.
     *
     * Note: This migration cannot be truly reversed as we don't know
     * what the original values were. The down method is left empty.
     */
    public function down(): void
    {
        echo "⚠️  This migration cannot be reversed as original values are unknown.\n";
        echo "⚠️  If you need to revert, restore from a database backup.\n";
    }
};
