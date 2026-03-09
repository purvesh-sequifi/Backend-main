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
            if (Schema::hasTable('reconciliation_finalize_history')) {
                if (! Schema::hasColumn('reconciliation_finalize_history', 'is_next_payroll')) {
                    $table->tinyInteger('is_next_payroll')->default(0)->nullable();
                }
                if (! Schema::hasColumn('reconciliation_finalize_history', 'is_mark_paid')) {
                    $table->tinyInteger('is_mark_paid')->default(0)->nullable();
                }
                if (! Schema::hasColumn('reconciliation_finalize_history', 'is_stop_payroll')) {
                    $table->tinyInteger('is_stop_payroll')->default(0)->nullable();
                }
                if (! Schema::hasColumn('reconciliation_finalize_history', 'ref_id')) {
                    $table->integer('ref_id')->nullable()->default(0);
                }
                if (! Schema::hasColumn('reconciliation_finalize_history', 'payroll_status')) {
                    $table->tinyInteger('payroll_status')->nullable()->default(1);
                }
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
