<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix net_epc precision issue in legacy_api_raw_data_histories table
     * Change from decimal(8,4) to decimal(16,8) to support up to 8 decimal places
     * for fee percentages after dividing by 100 (e.g., 2.023/100 = 0.02023)
     */
    public function up(): void
    {
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->decimal('net_epc', 16, 8)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->decimal('net_epc', 8, 4)->nullable()->change();
        });
    }
};
