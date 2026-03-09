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
        Schema::table('paystub_employee', function (Blueprint $table) {
            $table->tinyInteger('is_onetime_payment')->default(0)->after('company_lng');
            $table->unsignedBigInteger('one_time_payment_id')->nullable()->after('is_onetime_payment');
        });

        Schema::table('one_time_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('pay_frequency')->after('quickbooks_journal_entry_id')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
            $table->string('user_worker_type')->after('pay_frequency')->comment('1099, w2')->nullable();
            $table->date('pay_period_from')->nullable()->after('user_worker_type');
            $table->date('pay_period_to')->nullable()->after('pay_period_from');
        });

        Schema::table('w2_payroll_tax_deductions', function (Blueprint $table) {
            $table->tinyInteger('is_onetime_payment')->default(0)->after('response');
            $table->unsignedBigInteger('one_time_payment_id')->nullable()->after('is_onetime_payment');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('paystub_employee', function (Blueprint $table) {
            $table->dropColumn(['is_onetime_payment', 'one_time_payment_id']);
        });

        Schema::table('one_time_payments', function (Blueprint $table) {
            $table->dropColumn(['pay_frequency', 'user_worker_type', 'pay_period_from', 'pay_period_to']);
        });

        Schema::table('w2_payroll_tax_deductions', function (Blueprint $table) {
            $table->dropColumn(['is_onetime_payment', 'one_time_payment_id']);
        });
    }
};
