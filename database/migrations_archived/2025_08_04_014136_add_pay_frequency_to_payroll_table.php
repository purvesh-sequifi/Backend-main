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
        if (Schema::hasTable('payrolls') && !Schema::hasColumn('payrolls', 'worker_type')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->string('worker_type')->after('custom_payment')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payrolls') && !Schema::hasColumn('payrolls', 'pay_frequency')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('payroll_history') && !Schema::hasColumn('payroll_history', 'worker_type')) {
            Schema::table('payroll_history', function (Blueprint $table) {
                $table->string('worker_type')->after('custom_payment')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_history') && !Schema::hasColumn('payroll_history', 'pay_frequency')) {
            Schema::table('payroll_history', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('approvals_and_requests') && !Schema::hasColumn('approvals_and_requests', 'user_worker_type')) {
            Schema::table('approvals_and_requests', function (Blueprint $table) {
                $table->string('user_worker_type')->after('action_item_status')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('approvals_and_requests') && !Schema::hasColumn('approvals_and_requests', 'pay_frequency')) {
            Schema::table('approvals_and_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('approvals_and_requests_lock') && !Schema::hasColumn('approvals_and_requests_lock', 'user_worker_type')) {
            Schema::table('approvals_and_requests_lock', function (Blueprint $table) {
                $table->string('user_worker_type')->after('action_item_status')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('approvals_and_requests_lock') && !Schema::hasColumn('approvals_and_requests_lock', 'pay_frequency')) {
            Schema::table('approvals_and_requests_lock', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('clawback_settlements') && !Schema::hasColumn('clawback_settlements', 'user_worker_type')) {
            Schema::table('clawback_settlements', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('clawback_settlements') && !Schema::hasColumn('clawback_settlements', 'pay_frequency')) {
            Schema::table('clawback_settlements', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('clawback_settlements_lock') && !Schema::hasColumn('clawback_settlements_lock', 'user_worker_type')) {
            Schema::table('clawback_settlements_lock', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('clawback_settlements_lock') && !Schema::hasColumn('clawback_settlements_lock', 'pay_frequency')) {
            Schema::table('clawback_settlements_lock', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('payroll_adjustment_details') && !Schema::hasColumn('payroll_adjustment_details', 'user_worker_type')) {
            Schema::table('payroll_adjustment_details', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_adjustment_details') && !Schema::hasColumn('payroll_adjustment_details', 'pay_frequency')) {
            Schema::table('payroll_adjustment_details', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('payroll_adjustment_details_lock') && !Schema::hasColumn('payroll_adjustment_details_lock', 'user_worker_type')) {
            Schema::table('payroll_adjustment_details_lock', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_adjustment_details_lock') && !Schema::hasColumn('payroll_adjustment_details_lock', 'pay_frequency')) {
            Schema::table('payroll_adjustment_details_lock', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('payroll_deductions') && !Schema::hasColumn('payroll_deductions', 'user_worker_type')) {
            Schema::table('payroll_deductions', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_deductions') && !Schema::hasColumn('payroll_deductions', 'pay_frequency')) {
            Schema::table('payroll_deductions', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('payroll_deduction_locks') && !Schema::hasColumn('payroll_deduction_locks', 'user_worker_type')) {
            Schema::table('payroll_deduction_locks', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_deduction_locks') && !Schema::hasColumn('payroll_deduction_locks', 'pay_frequency')) {
            Schema::table('payroll_deduction_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('payroll_hourly_salary') && !Schema::hasColumn('payroll_hourly_salary', 'user_worker_type')) {
            Schema::table('payroll_hourly_salary', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_hourly_salary') && !Schema::hasColumn('payroll_hourly_salary', 'pay_frequency')) {
            Schema::table('payroll_hourly_salary', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('payroll_hourly_salary_lock') && !Schema::hasColumn('payroll_hourly_salary_lock', 'user_worker_type')) {
            Schema::table('payroll_hourly_salary_lock', function (Blueprint $table) {
                $table->string('user_worker_type')->after('is_move_to_recon')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_hourly_salary_lock') && !Schema::hasColumn('payroll_hourly_salary_lock', 'pay_frequency')) {
            Schema::table('payroll_hourly_salary_lock', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('payroll_overtimes') && !Schema::hasColumn('payroll_overtimes', 'user_worker_type')) {
            Schema::table('payroll_overtimes', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_overtimes') && !Schema::hasColumn('payroll_overtimes', 'pay_frequency')) {
            Schema::table('payroll_overtimes', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('payroll_overtimes_lock') && !Schema::hasColumn('payroll_overtimes_lock', 'user_worker_type')) {
            Schema::table('payroll_overtimes_lock', function (Blueprint $table) {
                $table->string('user_worker_type')->after('is_move_to_recon')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('payroll_overtimes_lock') && !Schema::hasColumn('payroll_overtimes_lock', 'pay_frequency')) {
            Schema::table('payroll_overtimes_lock', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('reconciliation_finalize_history') && !Schema::hasColumn('reconciliation_finalize_history', 'user_worker_type')) {
            Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
                $table->string('user_worker_type')->after('is_upfront')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('reconciliation_finalize_history') && !Schema::hasColumn('reconciliation_finalize_history', 'pay_frequency')) {
            Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('reconciliation_finalize_history_locks') && !Schema::hasColumn('reconciliation_finalize_history_locks', 'user_worker_type')) {
            Schema::table('reconciliation_finalize_history_locks', function (Blueprint $table) {
                $table->string('user_worker_type')->after('move_from_payroll_flag')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('reconciliation_finalize_history_locks') && !Schema::hasColumn('reconciliation_finalize_history_locks', 'pay_frequency')) {
            Schema::table('reconciliation_finalize_history_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('user_commission') && !Schema::hasColumn('user_commission', 'user_worker_type')) {
            Schema::table('user_commission', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('user_commission') && !Schema::hasColumn('user_commission', 'pay_frequency')) {
            Schema::table('user_commission', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('user_commission_lock') && !Schema::hasColumn('user_commission_lock', 'user_worker_type')) {
            Schema::table('user_commission_lock', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('user_commission_lock') && !Schema::hasColumn('user_commission_lock', 'pay_frequency')) {
            Schema::table('user_commission_lock', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('user_overrides') && !Schema::hasColumn('user_overrides', 'user_worker_type')) {
            Schema::table('user_overrides', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('user_overrides') && !Schema::hasColumn('user_overrides', 'pay_frequency')) {
            Schema::table('user_overrides', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('user_overrides_lock') && !Schema::hasColumn('user_overrides_lock', 'user_worker_type')) {
            Schema::table('user_overrides_lock', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('user_overrides_lock') && !Schema::hasColumn('user_overrides_lock', 'pay_frequency')) {
            Schema::table('user_overrides_lock', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('custom_field') && !Schema::hasColumn('custom_field', 'user_worker_type')) {
            Schema::table('custom_field', function (Blueprint $table) {
                $table->string('user_worker_type')->after('ref_id')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('custom_field') && !Schema::hasColumn('custom_field', 'pay_frequency')) {
            Schema::table('custom_field', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_frequency')->after('user_worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            });
        }
        if (Schema::hasTable('custom_field_history') && !Schema::hasColumn('custom_field_history', 'user_worker_type')) {
            Schema::table('custom_field_history', function (Blueprint $table) {
                $table->string('user_worker_type')->after('pay_period_to')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('custom_field_history') && !Schema::hasColumn('custom_field_history', 'pay_frequency')) {
            Schema::table('custom_field_history', function (Blueprint $table) {
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
        // 
    }
};
