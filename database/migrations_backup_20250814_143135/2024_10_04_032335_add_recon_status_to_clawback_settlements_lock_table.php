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
            $table->tinyInteger('recon_status')->default(1)->after('status')->comment('1 = Unpaid, 2 = Partially Paid, 3 = Fully Paid');
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
