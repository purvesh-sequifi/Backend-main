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
        if (Schema::hasTable('w2_payroll_tax_deductions') && !Schema::hasColumn('w2_payroll_tax_deductions', 'user_worker_type')) {
            Schema::table('w2_payroll_tax_deductions', function (Blueprint $table) {
                $table->string('user_worker_type')->after('pay_period_to')->comment('1099, w2')->nullable();
            });
        }
        if (Schema::hasTable('w2_payroll_tax_deductions') && !Schema::hasColumn('w2_payroll_tax_deductions', 'pay_frequency')) {
            Schema::table('w2_payroll_tax_deductions', function (Blueprint $table) {
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
        Schema::table('w2_payroll_tax_deductions', function (Blueprint $table) {
            //
        });
    }
};
