<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('legacy_api_raw_data_histories_log', function (Blueprint $table) {
            $table->id();

            // Log-specific fields
            $table->string('action_type', 10)->comment('Type of action: insert, update, delete');
            $table->unsignedBigInteger('original_id')->nullable()->comment('Reference to the original record ID');
            $table->unsignedBigInteger('changed_by')->nullable()->comment('User ID who made the change');
            $table->timestamp('changed_at')->nullable()->comment('When the change was made');

            // Fields from the legacy_api_raw_data_histories table
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
            $table->integer('setter_id')->nullable()->default(0);
            $table->string('sales_rep_name')->nullable();
            $table->integer('employee_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('initialAppointmentID')->nullable();
            $table->unsignedBigInteger('soldBy')->nullable();
            $table->unsignedBigInteger('soldBy2')->nullable();
            $table->string('initialStatusText')->nullable();
            $table->string('sales_rep_email')->nullable();
            $table->string('kw')->nullable();
            $table->string('balance_age', 20)->nullable()->default('0');
            $table->date('date_cancelled')->nullable();
            $table->date('customer_signoff')->nullable()->comment('Approved date');
            $table->date('m1_date')->nullable();
            $table->date('m2_date')->nullable();
            $table->string('product')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_code', 100)->nullable();
            $table->string('sale_product_name')->nullable();
            $table->double('gross_account_value', 11, 3)->nullable();
            $table->decimal('epc', 8, 4)->nullable();
            $table->decimal('net_epc', 8, 4)->nullable();
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
            $table->unsignedBigInteger('closer1_id')->nullable();
            $table->unsignedBigInteger('closer2_id')->nullable();
            $table->unsignedBigInteger('setter1_id')->nullable();
            $table->unsignedBigInteger('setter2_id')->nullable();
            $table->string('closer1_m1', 50)->nullable()->default('0');
            $table->string('closer2_m1', 50)->nullable()->default('0');
            $table->string('setter1_m1', 50)->nullable()->default('0');
            $table->string('setter2_m1', 50)->nullable()->default('0');
            $table->string('closer1_m2', 50)->nullable()->default('0');
            $table->string('closer2_m2', 50)->nullable()->default('0');
            $table->string('setter1_m2', 50)->nullable()->default('0');
            $table->string('setter2_m2', 50)->nullable()->default('0');
            $table->string('closer1_commission')->nullable()->default('0');
            $table->string('closer2_commission')->nullable()->default('0');
            $table->string('setter1_commission')->nullable()->default('0');
            $table->string('setter2_commission')->nullable()->default('0');
            $table->string('closer1_m1_paid_status', 100)->nullable();
            $table->string('closer2_m1_paid_status', 100)->nullable();
            $table->string('setter1_m1_paid_status', 100)->nullable();
            $table->string('setter2_m1_paid_status', 100)->nullable();
            $table->string('closer1_m2_paid_status', 100)->nullable();
            $table->string('closer2_m2_paid_status', 100)->nullable();
            $table->string('setter1_m2_paid_status', 100)->nullable();
            $table->string('setter2_m2_paid_status', 100)->nullable();
            $table->string('closer1_m1_paid_date', 100)->nullable();
            $table->string('closer2_m1_paid_date', 100)->nullable();
            $table->string('setter1_m1_paid_date', 100)->nullable();
            $table->string('setter2_m1_paid_date', 100)->nullable();
            $table->string('closer1_m2_paid_date', 100)->nullable();
            $table->string('closer2_m2_paid_date', 100)->nullable();
            $table->string('setter1_m2_paid_date', 100)->nullable();
            $table->string('setter2_m2_paid_date', 100)->nullable();
            $table->unsignedBigInteger('mark_account_status_id')->nullable();
            $table->string('pid_status')->nullable();
            $table->string('length_of_agreement')->nullable();
            $table->string('service_schedule')->nullable();
            $table->string('initial_service_cost')->nullable();
            $table->string('auto_pay')->nullable();
            $table->string('card_on_file')->nullable();
            $table->string('subscription_payment')->nullable();
            $table->string('service_completed')->nullable();
            $table->date('last_service_date')->nullable();
            $table->date('initial_service_date')->nullable();
            $table->string('bill_status')->nullable();
            $table->string('data_source_type')->nullable();
            $table->dateTime('source_created_at')->nullable()->comment('date when created_at data source');
            $table->dateTime('source_updated_at')->nullable()->comment('date when updated_at data source');
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->tinyInteger('import_to_sales')->nullable()->default(0);
            $table->bigInteger('excel_import_id')->nullable();
            $table->date('contract_sign_date')->nullable();
            $table->string('job_status')->nullable();
            $table->longText('trigger_date')->nullable();
            $table->json('customer_payment_json')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('action_type');
            $table->index('original_id');
            $table->index('changed_at');
            $table->index('pid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_api_raw_data_histories_log');
    }
};
