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
        Schema::create('legacy_api_data_null', function (Blueprint $table) {
            $table->id();
            $table->Integer('legacy_data_id')->nullable();
            $table->Integer('aveyo_hs_id')->nullable();
            // $table->string('aveyo_project')->nullable();
            $table->string('pid')->nullable(); // pid
            $table->unsignedBigInteger('weekly_sheet_id')->nullable();
            // $table->string('week')->nullable();
            $table->string('homeowner_id')->nullable();
            $table->string('proposal_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->text('customer_address')->nullable();
            $table->text('customer_address_2')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_state')->nullable();
            $table->string('location_code')->nullable();
            $table->string('customer_zip')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->Integer('setter_id')->nullable();
            // $table->Integer('closer_id')->nullable();
            // $table->string('setter_name')->nullable();
            // $table->string('closer_name')->nullable();
            $table->Integer('employee_id')->nullable();
            $table->string('sales_rep_name')->nullable();
            $table->string('sales_rep_email')->nullable();
            $table->string('install_partner')->nullable();
            $table->Integer('install_partner_id')->nullable();
            $table->date('customer_signoff')->nullable();
            $table->date('m1_date')->nullable();
            $table->date('scheduled_install')->nullable();
            $table->date('install_complete_date')->nullable();
            $table->date('contract_sign_date')->nullable();
            $table->date('m2_date')->nullable();
            $table->date('date_cancelled')->nullable();
            $table->date('inactive_date')->nullable();
            $table->date('return_sales_date')->nullable();
            $table->double('gross_account_value', 11, 3)->nullable();
            $table->double('cash_amount', 11, 3)->nullable();
            $table->double('loan_amount', 11, 3)->nullable();
            $table->double('kw', 11, 3)->nullable();
            $table->string('dealer_fee_percentage')->nullable();
            $table->string('dealer_fee_dollar')->nullable();
            $table->string('dealer_fee_amount')->nullable();
            $table->string('shows')->nullable();
            $table->string('redline')->nullable();
            $table->string('total_for_acct')->nullable();
            $table->string('prev_paid')->nullable();
            $table->string('last_date_pd')->nullable();
            $table->string('m1_this_week')->nullable();
            $table->string('install_m2_this_week')->nullable();
            $table->string('prev_deducted')->nullable();
            $table->string('cancel_deduction')->nullable();
            $table->string('lead_cost')->nullable();
            $table->string('adv_pay_back_amount')->nullable();
            $table->string('total_in_period')->nullable();
            $table->string('adders')->nullable();
            $table->string('cancel_fee')->nullable();
            $table->string('adders_description')->nullable();
            $table->string('funding_source')->nullable();
            $table->string('financing_rate')->nullable();
            $table->string('financing_term')->nullable();
            $table->string('product')->nullable();
            $table->float('epc')->nullable();
            $table->float('net_epc')->nullable();
            $table->string('status')->nullable();
            $table->string('type')->nullable()->default('Missing Info');
            $table->string('email_status')->nullable()->default('Missing Info');
            $table->string('sales_type')->nullable();
            $table->tinyInteger('action_status')->default('0')->nullable();
            $table->string('source_created_at')->nullable();
            $table->string('source_updated_at')->nullable();
            $table->string('data_source_type')->nullable();
            $table->string('sales_alert')->nullable();
            $table->string('missingrep_alert')->nullable();
            $table->string('closedpayroll_alert')->nullable();
            $table->string('locationredline_alert')->nullable();
            $table->string('repredline_alert')->nullable();
            $table->string('people_alert')->nullable();
            $table->string('closedpayroll_type')->nullable();
            $table->string('job_status')->nullable();

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
        Schema::dropIfExists('legacy_api_data_null');
    }
};
