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
        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            $table->string('customer_latitude', 20)->nullable()->after('customer_address_2');
            $table->string('customer_longitude', 20)->nullable()->after('customer_address_2');
            $table->string('prospect_id', 50)->nullable()->after('pid');

        });

        Schema::table('sale_masters', function (Blueprint $table) {
            $table->string('customer_latitude', 20)->nullable()->after('customer_address_2');
            $table->string('customer_longitude', 20)->nullable()->after('customer_address_2');
            $table->string('prospect_id', 50)->nullable()->after('pid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            //
            $table->dropColumn('customer_latitude');
            $table->dropColumn('customer_longitude');
            $table->dropColumn('prospect_id');
        });
        Schema::table('sale_masters', function (Blueprint $table) {
            //
            $table->dropColumn('customer_latitude');
            $table->dropColumn('customer_longitude');
            $table->dropColumn('prospect_id');
        });
    }
};
