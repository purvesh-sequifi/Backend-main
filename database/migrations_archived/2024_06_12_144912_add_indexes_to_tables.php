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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->index('user_id', 'payroll_user_id');
            $table->index(['pay_period_from', 'pay_period_to'], 'payrolls_pay_period');
        });

        Schema::table('user_commission', function (Blueprint $table) {
            $table->index('user_id', 'user_commission_user_id');
        });

        Schema::table('user_overrides', function (Blueprint $table) {
            $table->index('user_id', 'user_override_user_id');
        });

        Schema::table('clawback_settlements', function (Blueprint $table) {
            $table->index('user_id', 'clawback_settlement_user_id');
        });

        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->index('user_id', 'payroll_adjustment_user_id');
        });

        Schema::table('payroll_adjustment_details', function (Blueprint $table) {
            $table->index('user_id', 'payroll_adjustment_detail_user_id');
        });

        Schema::table('approvals_and_requests', function (Blueprint $table) {
            $table->index('user_id', 'approvals_and_request_user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex('payroll_user_id');
            $table->dropIndex('payrolls_pay_period');
        });

        Schema::table('user_commission', function (Blueprint $table) {
            $table->dropIndex('user_commission_user_id');
        });

        Schema::table('user_overrides', function (Blueprint $table) {
            $table->dropIndex('user_override_user_id');
        });

        Schema::table('clawback_settlements', function (Blueprint $table) {
            $table->dropIndex('clawback_settlement_user_id');
        });

        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->dropIndex('payroll_adjustment_user_id');
        });

        Schema::table('payroll_adjustment_details', function (Blueprint $table) {
            $table->dropIndex('payroll_adjustment_detail_user_id');
        });

        Schema::table('approvals_and_requests', function (Blueprint $table) {
            $table->dropIndex('approvals_and_request_user_id');
        });
    }
};
