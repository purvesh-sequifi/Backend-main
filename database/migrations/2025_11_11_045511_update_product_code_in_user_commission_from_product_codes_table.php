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
     * Update product_code in user_commission table from product_codes table
     * based on matching product_id
     */
    public function up(): void
    {
        // Check if both tables exist
        if (!Schema::hasTable('user_commission') || !Schema::hasTable('product_codes')) {
            echo "⚠️  Required tables do not exist. Skipping migration.\n";
            return;
        }

        echo "🔄 Starting product_code update in user_commission table...\n";

        // Get statistics before update
        $totalRecords = DB::table('user_commission')->count();
        $nullBefore = DB::table('user_commission')->whereNull('product_code')->count();
        
        echo "📊 Total records: {$totalRecords}\n";
        echo "📊 Records with NULL product_code before update: {$nullBefore}\n";

        // Update product_code in user_commission from product_codes table
        // Note: user_commission.product_code uses utf8mb3, so we convert for comparison
        DB::statement("
            UPDATE user_commission uc
            INNER JOIN product_codes pc ON uc.product_id = pc.product_id
            SET uc.product_code = pc.product_code
            WHERE uc.product_code IS NULL 
            OR uc.product_code = ''
            OR CONVERT(uc.product_code USING utf8mb4) COLLATE utf8mb4_general_ci != pc.product_code COLLATE utf8mb4_general_ci
        ");

        echo "✅ Update query executed.\n";

        // Get statistics after update
        $nullAfter = DB::table('user_commission')->whereNull('product_code')->count();
        $updated = $nullBefore - $nullAfter;

        echo "📊 Records with NULL product_code after update: {$nullAfter}\n";
        echo "✨ Estimated records updated: {$updated}\n";

        // Also update user_commission_lock if it exists
        if (Schema::hasTable('user_commission_lock')) {
            echo "\n🔄 Updating user_commission_lock table as well...\n";
            
            $lockTotalRecords = DB::table('user_commission_lock')->count();
            $lockNullBefore = DB::table('user_commission_lock')->whereNull('product_code')->count();
            
            echo "📊 Lock table - Total records: {$lockTotalRecords}\n";
            echo "📊 Lock table - Records with NULL product_code before update: {$lockNullBefore}\n";

            // Note: user_commission_lock uses utf8mb4_0900_ai_ci, product_codes uses utf8mb4_unicode_ci
            DB::statement("
                UPDATE user_commission_lock ucl
                INNER JOIN product_codes pc ON ucl.product_id = pc.product_id
                SET ucl.product_code = pc.product_code
                WHERE ucl.product_code IS NULL 
                OR ucl.product_code = ''
                OR ucl.product_code COLLATE utf8mb4_general_ci != pc.product_code COLLATE utf8mb4_general_ci
            ");

            $lockNullAfter = DB::table('user_commission_lock')->whereNull('product_code')->count();
            $lockUpdated = $lockNullBefore - $lockNullAfter;

            echo "📊 Lock table - Records with NULL product_code after update: {$lockNullAfter}\n";
            echo "✨ Lock table - Estimated records updated: {$lockUpdated}\n";
        }

        echo "\n🎉 Migration completed successfully!\n";
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
