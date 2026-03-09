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
            if (! Schema::hasColumn('reconciliation_finalize_history', 'is_upfront')) {
                $table->boolean('is_upfront')->default(0)->after('payroll_id');
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
