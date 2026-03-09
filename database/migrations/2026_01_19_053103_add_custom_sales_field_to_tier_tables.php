<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom sales field columns to tier-related tables
     * Uses hasColumn checks to avoid duplicate column errors
     */
    public function up(): void
    {
        if (!Schema::hasColumn('tiers_position_commisions', 'custom_sales_field_id')) {
            Schema::table('tiers_position_commisions', function (Blueprint $table) {
                $table->unsignedBigInteger('custom_sales_field_id')
                    ->nullable()
                    ->after('commission_type');
                $table->foreign('custom_sales_field_id')
                    ->references('id')
                    ->on('crmsale_custom_field');
            });
        }

        if (!Schema::hasColumn('tiers_position_upfronts', 'custom_sales_field_id')) {
            Schema::table('tiers_position_upfronts', function (Blueprint $table) {
                $table->unsignedBigInteger('custom_sales_field_id')
                    ->nullable()
                    ->after('upfront_type');
                $table->foreign('custom_sales_field_id')
                    ->references('id')
                    ->on('crmsale_custom_field');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiers_position_commisions', function (Blueprint $table) {
            $table->dropForeign(['custom_sales_field_id']);
            $table->dropColumn('custom_sales_field_id');
        });

        Schema::table('tiers_position_upfronts', function (Blueprint $table) {
            $table->dropForeign(['custom_sales_field_id']);
            $table->dropColumn('custom_sales_field_id');
        });
    }
};
