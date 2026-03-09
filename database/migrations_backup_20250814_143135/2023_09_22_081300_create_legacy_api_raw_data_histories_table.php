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
        Schema::create('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_id')->nullable();
            $table->string('pid')->nullable();
            $table->unsignedBigInteger('weekly_sheet_id')->nullable();
            $table->string('install_partner')->nullable();
            $table->integer('install_partner_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->text('customer_address')->nullable();
            $table->text('customer_address_2')->nullable();
            $table->string('customer_city', 100)->nullable();
            $table->string('customer_state', 100)->nullable();
            $table->string('location_code', 100)->nullable();
            $table->string('customer_zip', 100)->nullable();
            $table->string('customer_email', 200)->nullable();
            $table->string('customer_phone', 100)->nullable();
            $table->string('homeowner_id', 100)->nullable();
            $table->string('proposal_id', 100)->nullable();
            $table->integer('setter_id')->default(0)->nullable();
            $table->string('sales_rep_name', 100)->nullable();
            $table->integer('employee_id')->nullable();
            $table->string('sales_rep_email', 200)->nullable();
            $table->string('kw', 50)->nullable();
            $table->date('date_cancelled')->nullable();
            $table->date('customer_signoff')->nullable()->comment('Approved date');
            $table->date('m1_date')->nullable();
            $table->date('m2_date')->nullable();
            $table->string('product')->nullable();
            $table->double('gross_account_value', 11, 3)->default(0)->nullable();
            $table->double('epc', 8, 4)->default(0)->nullable();
            $table->double('net_epc', 8, 4)->default(0)->nullable();
            $table->string('dealer_fee_percentage', 100)->nullable();
            $table->string('dealer_fee_amount', 100)->nullable();
            $table->string('adders', 100)->nullable()->comment('SOW amount');
            $table->string('adders_description')->nullable();
            $table->string('redline', 100)->nullable();
            $table->string('total_amount_for_acct', 100)->nullable();
            $table->string('prev_amount_paid', 100)->nullable();
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
            $table->double('cash_amount', 11, 3)->default(0)->nullable();
            $table->double('loan_amount', 11, 3)->default(0)->nullable();
            $table->unsignedBigInteger('closer1_id')->nullable();
            $table->unsignedBigInteger('closer2_id')->nullable();
            $table->unsignedBigInteger('setter1_id')->nullable();
            $table->unsignedBigInteger('setter2_id')->nullable();
            $table->string('closer1_m1', 100)->default(0)->nullable();
            $table->string('closer2_m1', 100)->default(0)->nullable();
            $table->string('setter1_m1', 100)->default(0)->nullable();
            $table->string('setter2_m1', 100)->default(0)->nullable();
            $table->string('closer1_m2', 100)->default(0)->nullable();
            $table->string('closer2_m2', 100)->default(0)->nullable();
            $table->string('setter1_m2', 100)->default(0)->nullable();
            $table->string('setter2_m2', 100)->default(0)->nullable();
            $table->string('closer1_commission', 100)->default(0)->nullable();
            $table->string('closer2_commission', 100)->default(0)->nullable();
            $table->string('setter1_commission', 100)->default(0)->nullable();
            $table->string('setter2_commission', 100)->default(0)->nullable();
            $table->string('closer1_m1_paid_status', 50)->nullable();
            $table->string('closer2_m1_paid_status', 50)->nullable();
            $table->string('setter1_m1_paid_status', 50)->nullable();
            $table->string('setter2_m1_paid_status', 50)->nullable();
            $table->string('closer1_m2_paid_status', 50)->nullable();
            $table->string('closer2_m2_paid_status', 50)->nullable();
            $table->string('setter1_m2_paid_status', 50)->nullable();
            $table->string('setter2_m2_paid_status', 50)->nullable();
            $table->string('closer1_m1_paid_date', 50)->nullable();
            $table->string('closer2_m1_paid_date', 50)->nullable();
            $table->string('setter1_m1_paid_date', 50)->nullable();
            $table->string('setter2_m1_paid_date', 50)->nullable();
            $table->string('closer1_m2_paid_date', 50)->nullable();
            $table->string('closer2_m2_paid_date', 50)->nullable();
            $table->string('setter1_m2_paid_date', 50)->nullable();
            $table->string('setter2_m2_paid_date', 50)->nullable();
            $table->string('mark_account_status_id', 100)->nullable();
            $table->string('pid_status', 100)->nullable();
            $table->string('data_source_type', 100)->nullable();
            $table->dateTime('source_created_at')->nullable()->comment('date when created_at data source');
            $table->dateTime('source_updated_at')->nullable()->comment('date when updated_at data source');
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->tinyInteger('import_to_sales')->default('0');
            $table->date('contract_sign_date')->nullable();
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
        Schema::dropIfExists('legacy_api_raw_data_histories');
    }
};
