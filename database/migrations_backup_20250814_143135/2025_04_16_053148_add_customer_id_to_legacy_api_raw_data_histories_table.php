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
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('employee_id'); // adjust position as needed
            $table->unsignedBigInteger('initialAppointmentID')->nullable()->after('customer_id');
            $table->unsignedBigInteger('soldBy')->nullable()->after('initialAppointmentID');
            $table->unsignedBigInteger('soldBy2')->nullable()->after('soldBy');
            $table->string('initialStatusText')->nullable()->after('soldBy2');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->dropColumn(['customer_id', 'initialAppointmentID', 'soldBy', 'soldBy2']);
        });
    }
};
