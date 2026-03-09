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
        Schema::table('position_commission_deductions', function (Blueprint $table) {
            if (! Schema::hasColumn('position_commission_deductions', 'pay_period_from')) {
                $table->date('pay_period_from')->nullable()->after('changes_field');
            }
            if (! Schema::hasColumn('position_commission_deductions', 'pay_period_to')) {
                $table->date('pay_period_to')->nullable()->after('pay_period_from');
            }
        });

        Schema::table('user_deduction_history', function (Blueprint $table) {
            if (! Schema::hasColumn('user_deduction_history', 'pay_period_from')) {
                $table->date('pay_period_from')->nullable()->after('changes_field');
            }
            if (! Schema::hasColumn('user_deduction_history', 'pay_period_to')) {
                $table->date('pay_period_to')->nullable()->after('pay_period_from');
            }
        });

        Schema::table('onboarding_employee_deduction', function (Blueprint $table) {
            if (! Schema::hasColumn('onboarding_employee_deduction', 'pay_period_from')) {
                $table->date('pay_period_from')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('onboarding_employee_deduction', 'pay_period_to')) {
                $table->date('pay_period_to')->nullable()->after('pay_period_from');
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
        Schema::table('position_commission_deductions', function (Blueprint $table) {
            // $table->dropColumn('pay_period_from');
            // $table->dropColumn('pay_period_to');
        });
    }
};
