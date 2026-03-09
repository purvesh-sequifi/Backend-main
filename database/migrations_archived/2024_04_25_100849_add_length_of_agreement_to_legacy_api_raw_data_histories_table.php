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
            $table->string('length_of_agreement')->nullable()->after('pid_status');
            $table->string('service_schedule')->nullable()->after('length_of_agreement');
            $table->string('subscription_payment')->nullable()->after('service_schedule');
            $table->string('service_completed')->nullable()->after('subscription_payment');
            $table->date('last_service_date')->nullable()->after('service_completed');
            $table->string('bill_status')->nullable()->after('last_service_date');
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
            //
        });
    }
};
