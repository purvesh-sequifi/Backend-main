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
        Schema::table('clawback_settlements_lock', function (Blueprint $table) {
            $table->string('schema_name')->after('schema_type')->nullable();
            $table->string('schema_trigger')->after('schema_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clawback_settlements_lock', function (Blueprint $table) {
            //
        });
    }
};
