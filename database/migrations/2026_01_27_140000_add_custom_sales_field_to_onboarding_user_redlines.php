<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom_sales_field_id columns to onboarding_user_redlines table
     */
    public function up(): void
    {
        Schema::table('onboarding_user_redlines', function (Blueprint $table) {
            // Add custom_sales_field_id for commission
            if (!Schema::hasColumn('onboarding_user_redlines', 'custom_sales_field_id')) {
                $table->unsignedBigInteger('custom_sales_field_id')->nullable()->after('commission_type');
            }
            
            // Add upfront_custom_sales_field_id for upfront
            if (!Schema::hasColumn('onboarding_user_redlines', 'upfront_custom_sales_field_id')) {
                $table->unsignedBigInteger('upfront_custom_sales_field_id')->nullable()->after('upfront_sale_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onboarding_user_redlines', function (Blueprint $table) {
            if (Schema::hasColumn('onboarding_user_redlines', 'custom_sales_field_id')) {
                $table->dropColumn('custom_sales_field_id');
            }
            if (Schema::hasColumn('onboarding_user_redlines', 'upfront_custom_sales_field_id')) {
                $table->dropColumn('upfront_custom_sales_field_id');
            }
        });
    }
};
