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
            $table->string('sales_setter_name', 100)->nullable()->default(null)->after('setter_id');
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
            $table->dropColumn('sales_setter_name');
        });
    }
};
