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
        // Check if table already exists to prevent errors
        if (! Schema::hasTable('user_filter_config_items')) {
            Schema::create('user_filter_config_items', function (Blueprint $table) {
                $table->id();
                $table->integer('user_id')->nullable();
                $table->unsignedBigInteger('filter_id')->nullable();
                $table->integer('sale_master_id')->nullable();
                $table->string('pid')->nullable();
                $table->string('ticket_id')->nullable();
                $table->string('initialStatusText')->nullable();
                $table->string('appointment_id')->nullable();
                $table->unsignedBigInteger('closer1_id')->nullable();
                $table->unsignedBigInteger('setter1_id')->nullable();
                $table->unsignedBigInteger('closer2_id')->nullable();
                $table->unsignedBigInteger('setter2_id')->nullable();
                $table->text('closer1_name')->nullable();
                $table->text('setter1_name')->nullable();
                $table->text('closer2_name')->nullable();
                $table->text('setter2_name')->nullable();
                $table->text('prospect_id')->nullable();
                $table->text('panel_type')->nullable();
                $table->integer('panel_id')->nullable();
                $table->unsignedBigInteger('weekly_sheet_id')->nullable();
                $table->text('install_partner')->nullable();
                $table->integer('install_partner_id')->nullable();
                $table->text('customer_name')->nullable();
                $table->text('customer_address')->nullable();
                $table->text('customer_address_2')->nullable();
                $table->text('customer_state')->nullable();
                $table->text('customer_zip')->nullable();
                $table->text('customer_longitude')->nullable();
                $table->text('customer_latitude')->nullable();
                $table->text('customer_city')->nullable();
                $table->text('location_code')->nullable();
                $table->text('customer_email')->nullable();
                $table->text('customer_phone')->nullable();
                $table->text('homeowner_id')->nullable();
                $table->text('proposal_id')->nullable();
                $table->text('sales_rep_name')->nullable();
                $table->integer('employee_id')->nullable();
                $table->text('sales_rep_email')->nullable();
                $table->text('kw')->nullable();
                $table->text('balance_age')->nullable();
                $table->text('date_cancelled')->nullable();
                $table->date('customer_signoff')->nullable()->comment('Approved date');
                $table->date('m1_date')->nullable();
                $table->date('m2_date')->nullable();
                $table->text('product')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->text('product_code')->nullable();
                $table->text('sale_product_name')->nullable();
                $table->tinyInteger('is_exempted')->default(0);
                $table->double('total_commission_amount', 11, 2)->nullable();
                $table->double('total_override_amount', 11, 2)->default(0.00);
                $table->tinyInteger('milestone_trigger')->nullable();
                $table->double('gross_account_value', 15, 2)->nullable();
                $table->float('epc')->nullable();
                $table->float('net_epc')->nullable();
                $table->text('dealer_fee_percentage')->nullable();
                $table->text('dealer_fee_amount')->nullable();
                $table->text('adders')->nullable();
                $table->text('adders_description')->nullable()->comment('SOW amount');
                $table->text('state_id')->nullable();
                $table->text('m1_amount')->nullable();
                $table->text('total_amount_for_acct')->nullable();
                $table->text('prev_amount_paid')->nullable();
                $table->text('total_due')->nullable();
                $table->text('m2_amount')->nullable();
                $table->text('prev_deducted_amount')->nullable();
                $table->text('cancel_fee')->nullable();
                $table->text('cancel_deduction')->nullable();
                $table->text('lead_cost_amount')->nullable();
                $table->text('adv_pay_back_amount')->nullable();
                $table->text('total_amount_in_period')->nullable();
                $table->text('funding_source')->nullable();
                $table->text('financing_rate')->nullable();
                $table->text('financing_term')->nullable();
                $table->text('scheduled_install')->nullable();
                $table->date('install_complete_date')->nullable();
                $table->text('return_sales_date')->nullable();
                $table->double('cash_amount', 11, 3)->nullable();
                $table->double('loan_amount', 11, 2)->nullable();
                $table->text('length_of_agreement')->nullable();
                $table->text('service_schedule')->nullable();
                $table->text('initial_service_cost')->nullable();
                $table->text('auto_pay')->nullable();
                $table->text('card_on_file')->nullable();
                $table->text('subscription_payment')->nullable();
                $table->text('service_completed')->nullable();
                $table->date('last_service_date')->nullable();
                $table->date('last_date_pd')->nullable();
                $table->text('initial_service_date')->nullable();
                $table->text('bill_status')->nullable();
                $table->text('sales_type')->nullable();
                $table->text('m1_source_type')->nullable();
                $table->text('job_status')->nullable();
                $table->text('trigger_date')->nullable();
                $table->tinyInteger('sale_item_status')->default(0)->comment('0 = Old; 1 = In Action Item');
                $table->text('total_commission')->nullable();
                $table->text('projected_commission')->nullable();
                $table->text('total_override')->nullable();
                $table->text('data_source_type')->nullable();
                $table->text('redline')->nullable();
                $table->tinyInteger('projected_override')->default(0)->comment('0 = Non-Projected, 1 = Projected');
                $table->tinyInteger('action_item_status')->default(0);
                $table->string('import_status_reason')->nullable()->comment('Reason why record failed import (e.g., Invalid Sales Rep, Date Restriction)');
                $table->text('import_status_description')->nullable()->comment('Detailed description of import failure');

                $table->foreign('weekly_sheet_id')
                    ->references('id')
                    ->on('legacy_weekly_sheet')
                    ->onDelete('cascade');

                $table->timestamps();
            });
        } else {
            // Table already exists, skip creation
            echo "Table 'user_filter_config_items' already exists, skipping creation.\n";
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_filter_config_items');
    }
};
