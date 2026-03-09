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
        if (Schema::hasTable('reconciliation_finalize_history') && !Schema::hasColumn('reconciliation_finalize_history', 'is_onetime_payment')) {
            Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('pay_frequency')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('reconciliation_finalize_history') && !Schema::hasColumn('reconciliation_finalize_history', 'one_time_payment_id')) {
            Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }
        if (Schema::hasTable('reconciliation_finalize_history_locks') && !Schema::hasColumn('reconciliation_finalize_history_locks', 'is_onetime_payment')) {
            Schema::table('reconciliation_finalize_history_locks', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('pay_frequency')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('reconciliation_finalize_history_locks') && !Schema::hasColumn('reconciliation_finalize_history_locks', 'one_time_payment_id')) {
            Schema::table('reconciliation_finalize_history_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }

        if (Schema::hasTable('user_reconciliation_commissions') && !Schema::hasColumn('user_reconciliation_commissions', 'is_onetime_payment')) {
            Schema::table('user_reconciliation_commissions', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('payroll_id')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('user_reconciliation_commissions') && !Schema::hasColumn('user_reconciliation_commissions', 'one_time_payment_id')) {
            Schema::table('user_reconciliation_commissions', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }
        if (Schema::hasTable('user_reconciliation_commissions_lock') && !Schema::hasColumn('user_reconciliation_commissions_lock', 'is_onetime_payment')) {
            Schema::table('user_reconciliation_commissions_lock', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('payroll_id')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('user_reconciliation_commissions_lock') && !Schema::hasColumn('user_reconciliation_commissions_lock', 'one_time_payment_id')) {
            Schema::table('user_reconciliation_commissions_lock', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }

        if (Schema::hasTable('recon_commission_histories') && !Schema::hasColumn('recon_commission_histories', 'is_onetime_payment')) {
            Schema::table('recon_commission_histories', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('pay_frequency')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('recon_commission_histories') && !Schema::hasColumn('recon_commission_histories', 'one_time_payment_id')) {
            Schema::table('recon_commission_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }
        if (Schema::hasTable('recon_commission_history_locks') && !Schema::hasColumn('recon_commission_history_locks', 'is_onetime_payment')) {
            Schema::table('recon_commission_history_locks', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('pay_frequency')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('recon_commission_history_locks') && !Schema::hasColumn('recon_commission_history_locks', 'one_time_payment_id')) {
            Schema::table('recon_commission_history_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }

        if (Schema::hasTable('recon_override_history') && !Schema::hasColumn('recon_override_history', 'is_onetime_payment')) {
            Schema::table('recon_override_history', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('pay_frequency')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('recon_override_history') && !Schema::hasColumn('recon_override_history', 'one_time_payment_id')) {
            Schema::table('recon_override_history', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }
        if (Schema::hasTable('recon_override_history_locks') && !Schema::hasColumn('recon_override_history_locks', 'is_onetime_payment')) {
            Schema::table('recon_override_history_locks', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('pay_frequency')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('recon_override_history_locks') && !Schema::hasColumn('recon_override_history_locks', 'one_time_payment_id')) {
            Schema::table('recon_override_history_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }

        if (Schema::hasTable('recon_clawback_histories') && !Schema::hasColumn('recon_clawback_histories', 'is_onetime_payment')) {
            Schema::table('recon_clawback_histories', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('is_displayed')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('recon_clawback_histories') && !Schema::hasColumn('recon_clawback_histories', 'one_time_payment_id')) {
            Schema::table('recon_clawback_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }
        if (Schema::hasTable('recon_clawback_history_locks') && !Schema::hasColumn('recon_clawback_history_locks', 'is_onetime_payment')) {
            Schema::table('recon_clawback_history_locks', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('is_displayed')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('recon_clawback_history_locks') && !Schema::hasColumn('recon_clawback_history_locks', 'one_time_payment_id')) {
            Schema::table('recon_clawback_history_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }

        if (Schema::hasTable('recon_adjustments') && !Schema::hasColumn('recon_adjustments', 'is_onetime_payment')) {
            Schema::table('recon_adjustments', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('payroll_execute_status')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('recon_adjustments') && !Schema::hasColumn('recon_adjustments', 'one_time_payment_id')) {
            Schema::table('recon_adjustments', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }
        if (Schema::hasTable('recon_adjustment_locks') && !Schema::hasColumn('recon_adjustment_locks', 'is_onetime_payment')) {
            Schema::table('recon_adjustment_locks', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('payroll_execute_status')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('recon_adjustment_locks') && !Schema::hasColumn('recon_adjustment_locks', 'one_time_payment_id')) {
            Schema::table('recon_adjustment_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }

        if (Schema::hasTable('recon_deduction_histories') && !Schema::hasColumn('recon_deduction_histories', 'is_onetime_payment')) {
            Schema::table('recon_deduction_histories', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('is_stop_payroll')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('recon_deduction_histories') && !Schema::hasColumn('recon_deduction_histories', 'one_time_payment_id')) {
            Schema::table('recon_deduction_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
            });
        }
        if (Schema::hasTable('recon_deduction_history_locks') && !Schema::hasColumn('recon_deduction_history_locks', 'is_onetime_payment')) {
            Schema::table('recon_deduction_history_locks', function (Blueprint $table) {
                $table->string('is_onetime_payment')->after('is_stop_payroll')->comment('1 = One Time Payment, 0 = Normal')->nullable();
            });
        }
        if (Schema::hasTable('recon_deduction_history_locks') && !Schema::hasColumn('recon_deduction_history_locks', 'one_time_payment_id')) {
            Schema::table('recon_deduction_history_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('one_time_payment_id')->after('is_onetime_payment')->nullable();
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
        Schema::table('user_reconciliation_commissions', function (Blueprint $table) {
            //
        });
    }
};
