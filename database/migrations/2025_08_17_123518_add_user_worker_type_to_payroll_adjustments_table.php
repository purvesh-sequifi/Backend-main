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
        if (Schema::hasTable('payroll_adjustments') && !Schema::hasColumn('payroll_adjustments', 'user_worker_type')) {
            Schema::table('payroll_adjustments', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_adjustments') && !Schema::hasColumn('payroll_adjustments', 'pay_frequency')) {
            Schema::table('payroll_adjustments', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('payroll_adjustments_lock') && !Schema::hasColumn('payroll_adjustments_lock', 'user_worker_type')) {
            Schema::table('payroll_adjustments_lock', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_adjustments_lock') && !Schema::hasColumn('payroll_adjustments_lock', 'pay_frequency')) {
            Schema::table('payroll_adjustments_lock', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }


        if (Schema::hasTable('recon_commission_histories') && !Schema::hasColumn('recon_commission_histories', 'user_worker_type')) {
            Schema::table('recon_commission_histories', function (Blueprint $table) {
                $table->string('user_worker_type')->after('is_displayed')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('recon_commission_histories') && !Schema::hasColumn('recon_commission_histories', 'pay_frequency')) {
            Schema::table('recon_commission_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('recon_commission_history_locks') && !Schema::hasColumn('recon_commission_history_locks', 'user_worker_type')) {
            Schema::table('recon_commission_history_locks', function (Blueprint $table) {
                $table->string('user_worker_type')->after('is_deducted')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('recon_commission_history_locks') && !Schema::hasColumn('recon_commission_history_locks', 'pay_frequency')) {
            Schema::table('recon_commission_history_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }


        if (Schema::hasTable('recon_override_history') && !Schema::hasColumn('recon_override_history', 'user_worker_type')) {
            Schema::table('recon_override_history', function (Blueprint $table) {
                $table->string('user_worker_type')->after('percentage')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('recon_override_history') && !Schema::hasColumn('recon_override_history', 'pay_frequency')) {
            Schema::table('recon_override_history', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('recon_override_history_locks') && !Schema::hasColumn('recon_override_history_locks', 'user_worker_type')) {
            Schema::table('recon_override_history_locks', function (Blueprint $table) {
                $table->string('user_worker_type')->after('is_ineligible')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('recon_override_history_locks') && !Schema::hasColumn('recon_override_history_locks', 'pay_frequency')) {
            Schema::table('recon_override_history_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
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
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            //
        });
    }
};
