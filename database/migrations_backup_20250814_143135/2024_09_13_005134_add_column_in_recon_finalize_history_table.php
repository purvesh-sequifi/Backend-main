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
            if (! Schema::hasColumn('reconciliation_finalize_history', 'percentage_pay_amount')) {
                $table->double('percentage_pay_amount', 8, 2)->after('net_amount')->default(0);
            }
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
