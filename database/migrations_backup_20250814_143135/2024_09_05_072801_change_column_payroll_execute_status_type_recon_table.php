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
        /* recon commission */
        if (Schema::hasTable('recon_commission_histories')) {
            Schema::table('recon_commission_histories', function (Blueprint $table) {
                if (Schema::hasColumn('recon_commission_histories', 'payroll_execute_status')) {
                    $table->string('payroll_execute_status')->default(0)->change();
                }
                if (Schema::hasColumn('recon_commission_histories', 'user_id')) {
                    $table->integer('user_id')->nullable()->change();
                }
            });
        }
        if (Schema::hasTable('recon_commission_history_locks')) {
            Schema::table('recon_commission_history_locks', function (Blueprint $table) {
                if (Schema::hasColumn('recon_commission_history_locks', 'payroll_execute_status')) {
                    $table->string('payroll_execute_status')->default(0)->change();
                }
                if (Schema::hasColumn('recon_commission_history_locks', 'user_id')) {
                    $table->integer('user_id')->nullable()->change();
                }
            });
        }
        /* recon override changes */
        if (Schema::hasTable('recon_override_history')) {
            Schema::table('recon_override_history', function (Blueprint $table) {
                if (! Schema::hasColumn('recon_override_history', 'overrides_settlement_type')) {
                    $table->enum('overrides_settlement_type', ['reconciliation', 'during_m2'])->default('reconciliation');
                }
            });
        }
        if (Schema::hasTable('recon_override_history_locks')) {
            Schema::table('recon_override_history_locks', function (Blueprint $table) {
                if (! Schema::hasColumn('recon_override_history_locks', 'overrides_settlement_type')) {
                    $table->enum('overrides_settlement_type', ['reconciliation', 'during_m2'])->default('reconciliation');
                }
            });
        }

        /* recon clawback changes */
        if (Schema::hasTable('recon_clawback_histories')) {
            Schema::table('recon_clawback_histories', function (Blueprint $table) {
                if (Schema::hasColumn('recon_clawback_histories', 'payroll_execute_status')) {
                    $table->string('payroll_execute_status')->default(0)->change();
                }
                if (Schema::hasColumn('recon_clawback_histories', 'user_id')) {
                    $table->integer('user_id')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('recon_clawback_history_locks')) {
            Schema::table('recon_clawback_history_locks', function (Blueprint $table) {
                if (Schema::hasColumn('recon_clawback_history_locks', 'payroll_execute_status')) {
                    $table->string('payroll_execute_status')->default(0)->change();
                }
                if (Schema::hasColumn('recon_clawback_history_locks', 'user_id')) {
                    $table->integer('user_id')->nullable()->change();
                }
            });
        }

        /* recon adjustment changes */
        if (Schema::hasTable('recon_adjustments')) {
            Schema::table('recon_adjustments', function (Blueprint $table) {
                if (Schema::hasColumn('recon_adjustments', 'payroll_execute_status')) {
                    $table->string('payroll_execute_status')->default(0)->change();
                }
                if (Schema::hasColumn('recon_adjustments', 'user_id')) {
                    $table->integer('user_id')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('recon_adjustment_locks')) {
            Schema::table('recon_adjustment_locks', function (Blueprint $table) {
                if (Schema::hasColumn('recon_adjustment_locks', 'payroll_execute_status')) {
                    $table->string('payroll_execute_status')->default(0)->change();
                }
                if (Schema::hasColumn('recon_adjustment_locks', 'user_id')) {
                    $table->integer('user_id')->nullable()->change();
                }
            });
        }

        /* recon adjustment changes */
        if (Schema::hasTable('recon_deduction_histories')) {
            Schema::table('recon_deduction_histories', function (Blueprint $table) {
                if (Schema::hasColumn('recon_deduction_histories', 'payroll_executed_status')) {
                    $table->string('payroll_executed_status')->default(0)->change();
                }
                if (Schema::hasColumn('recon_deduction_histories', 'user_id')) {
                    // $table->integer("user_id")->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('recon_deduction_history_locks')) {
            Schema::table('recon_deduction_history_locks', function (Blueprint $table) {
                if (Schema::hasColumn('recon_deduction_history_locks', 'payroll_executed_status')) {
                    $table->string('payroll_executed_status')->default(0)->change();
                }
                if (Schema::hasColumn('recon_deduction_history_locks', 'user_id')) {
                    // $table->integer("user_id")->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
