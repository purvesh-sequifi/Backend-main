<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds custom_field_values column to store imported custom sales field values
     * from Excel imports. This enables the Custom Sales Fields feature to work
     * correctly with Excel imported sales - commission/override calculations
     * will have access to the imported values.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('legacy_api_raw_data_histories', 'custom_field_values')) {
            Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
                $table->json('custom_field_values')->nullable()->after('mapped_fields')
                    ->comment('JSON object of custom field values imported from Excel {field_id: value}');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('legacy_api_raw_data_histories', 'custom_field_values')) {
            Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
                $table->dropColumn('custom_field_values');
            });
        }
    }
};
