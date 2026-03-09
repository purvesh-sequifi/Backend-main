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
        Schema::create('legacy_excel_raw_data', function (Blueprint $table) {
            $table->id();
            $table->string('ct')->nullable();
            $table->unsignedBigInteger('weekly_sheet_id')->nullable();
            $table->string('affiliate')->nullable();
            $table->string('pid')->nullable();
            $table->string('install_partner')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('sales_rep_name')->nullable();
            $table->string('sales_rep_email')->nullable();
            $table->string('sales_setter_email')->nullable();
            $table->double('kw', 8, 3)->nullable();
            $table->date('cancel_date')->nullable();
            $table->date('approved_date')->nullable();
            $table->date('m1_date')->nullable();
            $table->date('m2_date')->nullable();
            $table->string('state')->nullable();
            $table->string('product')->nullable();
            $table->double('gross_account_value', 11, 3)->nullable();
            $table->double('epc', 11, 3)->nullable();
            $table->double('net_epc', 11, 3)->nullable();
            $table->double('dealer_fee_percentage', 6, 3)->nullable();
            $table->double('dealer_fee_dollar', 11, 3)->nullable();
            $table->double('show', 11, 3)->nullable();
            $table->double('redline', 11, 3)->nullable();
            $table->double('total_for_acct', 11, 3)->nullable();
            $table->double('prev_paid', 11, 3)->nullable();
            $table->date('last_date_pd')->nullable();
            $table->double('m1_this_week', 11, 3)->nullable();
            $table->double('install_m2_this_week', 11, 3)->nullable();
            $table->double('prev_deducted', 11, 3)->nullable();
            $table->double('cancel_fee', 11, 3)->nullable();
            $table->string('cancel_deduction', 11, 3)->nullable();
            $table->double('lead_cost', 11, 3)->nullable();
            $table->string('adv_pay_back_amount')->nullable();
            $table->double('total_in_period', 11, 3)->nullable();
            $table->date('inactive_date')->nullable();
            $table->string('data_source_type')->nullable();
            $table->timestamps();
            $table->foreign('weekly_sheet_id')->references('id')
                ->on('legacy_weekly_sheet')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('legacy_excel_raw_data');
    }
};
