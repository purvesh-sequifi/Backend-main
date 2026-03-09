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
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->unsignedBigInteger('closer1_id')->nullable()->after('pid');
            $table->unsignedBigInteger('setter1_id')->nullable()->after('closer1_id');
            $table->unsignedBigInteger('closer2_id')->nullable()->after('setter1_id');
            $table->unsignedBigInteger('setter2_id')->nullable()->after('closer2_id');
            $table->string('closer1_name')->nullable()->after('setter2_id');
            $table->string('setter1_name')->nullable()->after('closer1_name');
            $table->string('closer2_name')->nullable()->after('setter1_name');
            $table->string('setter2_name')->nullable()->after('closer2_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_masters', function (Blueprint $table) {
            //
        });
    }
};
