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
            if (! Schema::hasColumn('reconciliation_finalize_history', 'finalize_count')) {
                $table->string('finalize_count', 10)->default(0);
            }
        });
        Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliation_finalize_history', 'payroll_execute_status')) {
                $table->string('payroll_execute_status', 10)->default(0);
            }
            if (! Schema::hasColumn('reconciliation_finalize_history', 'is_displayed')) {
                $table->enum('is_displayed', ['0', '1'])->default('1')->after('is_stop_payroll')->comment('0 = Old, 1 = In Display');
            }
        });
        Schema::table('reconciliation_finalize_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliation_finalize_history_locks', 'finalize_count')) {
                $table->string('finalize_count', 10)->default(0);
            }
        });
        Schema::table('reconciliation_finalize_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliation_finalize_history_locks', 'payroll_execute_status')) {
                $table->string('payroll_execute_status', 10)->default(0);
            }
            if (! Schema::hasColumn('reconciliation_finalize_history_locks', 'is_displayed')) {
                $table->enum('is_displayed', ['0', '1'])->default('1')->after('is_stop_payroll')->comment('0 = Old, 1 = In Display');
            }
        });

        Schema::table('reconciliations_adjustement', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliations_adjustement', 'payroll_execute_status')) {
                $table->string('payroll_execute_status', 10)->default(0);
            }
            if (! Schema::hasColumn('reconciliation_finalize_history_locks', 'is_displayed')) {
                $table->enum('is_displayed', ['0', '1'])->default('1')->after('is_stop_payroll')->comment('0 = Old, 1 = In Display');
            }
        });
        Schema::table('reconciliations_adjustement_details', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliations_adjustement_details', 'payroll_execute_status')) {
                $table->string('payroll_execute_status', 10)->default(0);
            }
            if (! Schema::hasColumn('reconciliation_finalize_history_locks', 'is_displayed')) {
                $table->enum('is_displayed', ['0', '1'])->default('1')->after('is_stop_payroll')->comment('0 = Old, 1 = In Display');
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
        Schema::table('recon_finalize_history', function (Blueprint $table) {
            //
        });
    }
};
