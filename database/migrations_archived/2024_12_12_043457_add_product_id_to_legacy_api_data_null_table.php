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
            $table->unsignedBigInteger('product_id')->nullable()->after('product');
            $table->string('product_code')->nullable()->after('product_id');
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
        });
    }
};
