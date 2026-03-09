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
            Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
                $table->json('customer_payment_json')->nullable()->after('trigger_date');
            });
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
            Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
                $table->dropColumn('customer_payment_json');
            });
        });
    }
};
