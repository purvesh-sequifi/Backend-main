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
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'worker_type')) {
                $table->dropColumn('worker_type');
            }
            if (Schema::hasColumn('users', 'pay_type')) {
                $table->dropColumn('pay_type');
            }
            if (Schema::hasColumn('users', 'pay_rate')) {
                $table->dropColumn('pay_rate');
            }
            if (Schema::hasColumn('users', 'pay_rate_type')) {
                $table->dropColumn('pay_rate_type');
            }
            if (Schema::hasColumn('users', 'pto_hours')) {
                $table->dropColumn('pto_hours');
            }
            if (Schema::hasColumn('users', 'unused_pto_expires')) {
                $table->dropColumn('unused_pto_expires');
            }
            if (Schema::hasColumn('users', 'expected_weekly_hours')) {
                $table->dropColumn('expected_weekly_hours');
            }
            if (Schema::hasColumn('users', 'overtime_rate')) {
                $table->dropColumn('overtime_rate');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('worker_type', 50)->default('1099')->nullable()->comment('W9, 1099')->after('group_id');
            $table->string('pay_type', 50)->nullable()->comment('Hourly, Salary')->after('office_stack_overrides_amount');
            $table->string('pay_rate', 50)->default(0)->nullable()->after('pay_type');
            $table->string('pay_rate_type', 50)->nullable()->comment('Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly')->after('pay_rate');
            $table->string('pto_hours', 50)->default('0')->nullable()->after('pay_rate_type');
            $table->string('unused_pto_expires', 100)->nullable()->comment('Monthly, Annually, Accrues Continuously')->after('pto_hours');
            $table->string('expected_weekly_hours', 50)->nullable()->after('unused_pto_expires');
            $table->string('overtime_rate', 50)->nullable()->after('expected_weekly_hours');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
