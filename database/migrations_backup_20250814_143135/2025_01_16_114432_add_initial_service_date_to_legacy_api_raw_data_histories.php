<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Update 'legacy_api_raw_data_histories' table
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->date('initial_service_date')->nullable()->after('last_service_date');
        });

        // Update 'legacy_api_data_null' table
        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            $table->date('initial_service_date')->nullable()->after('last_service_date');
            $table->string('trigger_date')->nullable()->after('job_status');
        });

        // Update 'sale_masters' table
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->date('initial_service_date')->nullable()->after('last_service_date');
            $table->string('trigger_date')->nullable()->after('job_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Rollback changes for 'legacy_api_raw_data_histories' table
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->dropColumn('initial_service_date');
        });

        // Rollback changes for 'legacy_api_data_null' table
        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            $table->dropColumn('initial_service_date');
            $table->dropColumn('trigger_date');
        });

        // Rollback changes for 'sale_masters' table
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->dropColumn('initial_service_date');
            $table->dropColumn('trigger_date');
        });
    }
};
