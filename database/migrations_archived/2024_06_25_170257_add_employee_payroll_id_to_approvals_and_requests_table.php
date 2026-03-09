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
        Schema::table('approvals_and_requests', function (Blueprint $table) {
            if (Schema::hasColumn('approvals_and_requests', 'employee_payroll_id')) {
                $table->dropColumn('employee_payroll_id');
            }
            if (Schema::hasColumn('approvals_and_requests', 'start_date')) {
                $table->dropColumn('start_date');
            }
            if (Schema::hasColumn('approvals_and_requests', 'end_date')) {
                $table->dropColumn('end_date');
            }
            if (Schema::hasColumn('approvals_and_requests', 'adjustment_date')) {
                $table->dropColumn('adjustment_date');
            }
            if (Schema::hasColumn('approvals_and_requests', 'pto_hours_perday')) {
                $table->dropColumn('pto_hours_perday');
            }
            if (Schema::hasColumn('approvals_and_requests', 'clock_in')) {
                $table->dropColumn('clock_in');
            }
            if (Schema::hasColumn('approvals_and_requests', 'clock_out')) {
                $table->dropColumn('clock_out');
            }
            if (Schema::hasColumn('approvals_and_requests', 'lunch_adjustment')) {
                $table->dropColumn('lunch_adjustment');
            }
            if (Schema::hasColumn('approvals_and_requests', 'break_adjustment')) {
                $table->dropColumn('break_adjustment');
            }
            if (Schema::hasColumn('approvals_and_requests', 'declined_by')) {
                $table->dropColumn('declined_by');
            }
        });

        Schema::table('approvals_and_requests', function (Blueprint $table) {
            $table->bigInteger('employee_payroll_id')->nullable()->after('payroll_id');
            $table->date('start_date')->nullable()->after('image');
            $table->date('end_date')->nullable()->after('start_date');
            $table->date('adjustment_date')->nullable()->after('end_date');
            $table->string('pto_hours_perday')->nullable()->after('adjustment_date');
            $table->dateTime('clock_in')->nullable()->after('pto_hours_perday');
            $table->dateTime('clock_out')->nullable()->after('clock_in');
            $table->string('lunch_adjustment')->nullable()->after('clock_out');
            $table->string('break_adjustment')->nullable()->after('lunch_adjustment');
            $table->bigInteger('declined_by')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('approvals_and_requests', function (Blueprint $table) {
            //
        });
    }
};
