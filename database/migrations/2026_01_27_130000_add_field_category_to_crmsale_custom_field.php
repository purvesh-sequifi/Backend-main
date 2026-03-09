<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds field_category column to crmsale_custom_field table
     */
    public function up(): void
    {
        Schema::table('crmsale_custom_field', function (Blueprint $table) {
            if (!Schema::hasColumn('crmsale_custom_field', 'field_category')) {
                $table->string('field_category', 50)->nullable()->default('custom_sales')->after('sort_order');
            }
        });

        // Set default value for existing records
        \DB::table('crmsale_custom_field')
            ->whereNull('field_category')
            ->update(['field_category' => 'custom_sales']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crmsale_custom_field', function (Blueprint $table) {
            if (Schema::hasColumn('crmsale_custom_field', 'field_category')) {
                $table->dropColumn('field_category');
            }
        });
    }
};
