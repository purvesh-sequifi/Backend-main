<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add performance indexes to user_organization_history table
     * to optimize setterCloserListByEffectiveDate API queries
     *
     * @return void
     */
    public function up()
    {
        // Use ALGORITHM=INPLACE to prevent table locks on large tables
        // Note: Laravel's Schema builder creates indexes with INPLACE by default in MySQL 5.6+,
        // but we explicitly use DB::statement for older MySQL versions or explicit control

        // Check MySQL version to determine if we can use INPLACE
        $mysqlVersion = DB::select("SELECT VERSION() as version")[0]->version ?? '0.0.0';
        $useInplace = version_compare($mysqlVersion, '5.6.0', '>=');

        if ($useInplace) {
            // Use Schema builder (defaults to INPLACE in MySQL 5.6+)
            Schema::table('user_organization_history', function (Blueprint $table) {
                // Index for ROW_NUMBER() window function query (optimizes effective_date filtering with user_id)
                // This index can be used for both user_id-first and effective_date-first queries via left-prefix
                $table->index(['user_id', 'effective_date', 'id'], 'idx_user_effective_id');

                // Index for position filtering (optimizes position_id queries with effective_date)
                $table->index(['position_id', 'effective_date'], 'idx_position_effective');

                // Index for sub_position_id filtering (optimizes sub_position_id queries)
                $table->index(['sub_position_id', 'effective_date'], 'idx_subposition_effective');
            });
        } else {
            // For older MySQL versions, use explicit ALGORITHM=INPLACE via raw SQL
            DB::statement('ALTER TABLE user_organization_history ADD INDEX idx_user_effective_id (user_id, effective_date, id) ALGORITHM=INPLACE');
            DB::statement('ALTER TABLE user_organization_history ADD INDEX idx_position_effective (position_id, effective_date) ALGORITHM=INPLACE');
            DB::statement('ALTER TABLE user_organization_history ADD INDEX idx_subposition_effective (sub_position_id, effective_date) ALGORITHM=INPLACE');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_organization_history', function (Blueprint $table) {
            $table->dropIndex('idx_user_effective_id');
            $table->dropIndex('idx_position_effective');
            $table->dropIndex('idx_subposition_effective');
        });
    }
};

