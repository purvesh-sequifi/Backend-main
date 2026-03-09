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
        Schema::create('sale_masters', function (Blueprint $table) {
            $table->id();
            $table->string('pid')->nullable(); // PID
            $table->unsignedBigInteger('weekly_sheet_id')->nullable();
            $table->string('install_partner')->nullable();
            $table->Integer('install_partner_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->text('customer_address')->nullable();
            $table->text('customer_address_2')->nullable();
            $table->string('customer_city')->nullable();
            $table->Integer('state_id')->nullable();
            $table->string('customer_state')->nullable();
            $table->string('location_code')->nullable();
            $table->string('customer_zip')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('homeowner_id')->nullable();
            $table->string('proposal_id')->nullable();
            $table->string('sales_rep_name')->nullable();
            $table->Integer('employee_id')->nullable();
            $table->string('sales_rep_email')->nullable();
            $table->string('kw')->nullable();
            $table->date('date_cancelled')->nullable();
            $table->date('customer_signoff')->nullable()->comment('Approved date'); // Approved date
            $table->date('m1_date')->nullable();
            $table->date('m2_date')->nullable();
            $table->string('product')->nullable();
            $table->double('gross_account_value', 11, 3)->nullable();
            $table->double('epc', 11, 3)->nullable();
            $table->double('net_epc', 11, 3)->nullable();
            $table->string('dealer_fee_percentage')->nullable();
            $table->string('dealer_fee_amount')->nullable();
            $table->string('adders')->nullable()->comment('SOW amount');
            $table->string('adders_description')->nullable();
            $table->string('redline')->nullable();
            $table->string('total_amount_for_acct')->nullable();
            $table->string('prev_amount_paid')->nullable();
            $table->date('last_date_pd')->nullable();
            $table->string('m1_amount')->nullable();
            $table->string('m2_amount')->nullable();
            $table->string('prev_deducted_amount')->nullable();
            $table->string('cancel_fee')->nullable();
            $table->string('cancel_deduction')->nullable();
            $table->string('lead_cost_amount')->nullable();
            $table->string('adv_pay_back_amount')->nullable();
            $table->string('total_amount_in_period')->nullable();
            $table->string('funding_source')->nullable();
            $table->string('financing_rate')->nullable();
            $table->string('financing_term')->nullable();
            $table->date('scheduled_install')->nullable();
            $table->date('install_complete_date')->nullable();
            $table->date('return_sales_date')->nullable();
            $table->double('cash_amount', 11, 3)->nullable();
            $table->double('loan_amount', 11, 3)->nullable();
            $table->string('sales_type')->nullable();
            $table->foreign('weekly_sheet_id')->references('id')
                ->on('legacy_weekly_sheet')->onDelete('cascade');
            $table->string('data_source_type')->nullable();
            $table->string('job_status')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sale_masters');
    }
};
