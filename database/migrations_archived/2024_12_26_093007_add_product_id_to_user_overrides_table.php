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
        Schema::table('user_overrides', function (Blueprint $table) {
            $table->bigInteger('product_id')->after('user_id')->nullable();
            $table->string('product_code')->after('pid')->nullable();
        });

        Schema::table('user_overrides_lock', function (Blueprint $table) {
            $table->bigInteger('product_id')->after('user_id')->nullable();
            $table->string('product_code')->after('pid')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_overrides', function (Blueprint $table) {
            //
        });
    }
};
