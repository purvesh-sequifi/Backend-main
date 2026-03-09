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
        Schema::table('legacy_api_raw_data', function (Blueprint $table) {
            if (! Schema::hasColumn('legacy_api_raw_data', 'ticket_id')) {
                $table->string('ticket_id', 20)->nullable()->after('pid');
            }
            if (! Schema::hasColumn('legacy_api_raw_data', 'appointment_id')) {
                $table->string('appointment_id', 20)->nullable()->after('ticket_id');
            }
            if (! Schema::hasColumn('legacy_api_raw_data', 'balance_age')) {
                $table->string('balance_age', 20)->nullable()->default(0)->after('kw');
            }
            if (! Schema::hasColumn('legacy_api_raw_data', 'job_status')) {
                $table->string('job_status', 20)->nullable()->after('data_source_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('legacy_api_raw_data', function (Blueprint $table) {
            //
        });
    }
};
