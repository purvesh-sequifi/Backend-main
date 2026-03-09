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
        Schema::create('pocomos_raw_data', function (Blueprint $table) {
            $table->id();
            $table->string('pcc_id')->nullable();
            $table->string('contract_id')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('customer_first_name')->nullable();
            $table->string('customer_last_name')->nullable();
            $table->string('customer_status')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_external_account_id')->nullable();
            $table->text('customer_contact_address')->nullable();
            $table->string('customer_street')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_zip')->nullable();
            $table->string('customer_state')->nullable();
            $table->string('last_service_date')->nullable();
            $table->string('map_code')->nullable();
            $table->string('preferred_tech')->nullable();
            $table->string('contract_date')->nullable();
            $table->string('account_sign_up_start_date')->nullable();
            $table->string('sales_status')->nullable();
            $table->decimal('initial_price', 10, 2)->nullable();
            $table->decimal('recurring_price', 10, 2)->nullable();
            $table->decimal('balance', 10, 2)->nullable();
            $table->string('days_past_due')->nullable();
            $table->string('card_on_file')->nullable();
            $table->date('job_date')->nullable();
            $table->string('initial_date')->nullable();
            $table->string('contract_name')->nullable();
            $table->string('service_type')->nullable();
            $table->string('service_frequency')->nullable();
            $table->string('marketing_type')->nullable();
            $table->decimal('original_contract_value', 10, 2)->nullable();
            $table->decimal('first_year_contract_value', 10, 2)->nullable();
            $table->decimal('balance_credit', 10, 2)->nullable();
            $table->string('autopay')->nullable();
            $table->string('pay_level')->nullable();
            $table->string('salesperson_name')->nullable();
            $table->string('profile_external_id')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('salesperson_email')->nullable();
            $table->string('contract_cancelled_date')->nullable();
            $table->string('agreement_length')->nullable();

            // Additional fields
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_modified')->nullable();
            $table->string('sync_batch_id', 255)->nullable();
            $table->text('sync_notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pocomos_raw_data');
    }
};
