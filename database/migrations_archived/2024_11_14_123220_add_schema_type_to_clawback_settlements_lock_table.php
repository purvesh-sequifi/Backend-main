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
            $table->string('schema_type')->nullable()->after('adders_type');
            $table->tinyInteger('is_last')->default(0)->comment('Default 0, 1 = When last date hits')->after('recon_status');
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
