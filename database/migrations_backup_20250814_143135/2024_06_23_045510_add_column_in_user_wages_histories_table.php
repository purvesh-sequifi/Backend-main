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
        Schema::table('user_wages_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('user_wages_histories', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('overtime_rate');
            }

            $table->enum('old_pay_type', ['Hourly', 'Salary'])->nullable()->after('effective_date');
            $table->decimal('old_pay_rate', 10, 2)->nullable()->after('old_pay_type');
            $table->decimal('old_pto_hours', 10, 2)->nullable()->after('old_pay_rate');
            $table->enum('old_unused_pto', ['Expires Monthly', 'Expires Annually', 'Accrues Continuously'])->nullable()->after('old_pto_hours');
            $table->decimal('old_expected_weekly_hours', 10, 2)->nullable()->after('old_unused_pto');
            $table->decimal('old_overtime_rate', 10, 2)->nullable()->after('old_expected_weekly_hours');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_wages_histories', function (Blueprint $table) {
            //
        });
    }
};
