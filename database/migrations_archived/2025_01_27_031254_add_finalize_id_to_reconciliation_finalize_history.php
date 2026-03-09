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
        Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
            $table->unsignedBigInteger('finalize_id')->after('pid');
            $table->double('remaining')->default(0)->after('adjustments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
            //
        });
    }
};
