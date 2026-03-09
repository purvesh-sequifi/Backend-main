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
        Schema::table('clawback_settlements', function (Blueprint $table) {
            $table->string('redline')->nullable()->after('during');
            $table->string('redline_type')->nullable()->comment('Fixed, Shift Based on Location, Shift Based on Product, Shift Based on Product & Location')->after('redline');
        });
        Schema::table('clawback_settlements_lock', function (Blueprint $table) {
            $table->string('redline')->nullable()->after('during');
            $table->string('redline_type')->nullable()->comment('Fixed, Shift Based on Location, Shift Based on Product, Shift Based on Product & Location')->after('redline');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clawback_settlements', function (Blueprint $table) {
            //
        });
    }
};
