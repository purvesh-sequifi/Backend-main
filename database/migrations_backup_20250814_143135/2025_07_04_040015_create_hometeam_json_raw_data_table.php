<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHometeamJsonRawDataTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hometeam_json_raw_data', function (Blueprint $table) {
            $table->id();
            $table->string('pid')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_address')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_state')->nullable();
            $table->string('customer_zip')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('sales_rep_name')->nullable();
            $table->string('sales_rep_email')->nullable();
            $table->string('install_partner')->nullable();
            $table->date('customer_signoff')->nullable();
            $table->date('m1_date')->nullable();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->date('date_cancelled')->nullable();
            $table->date('last_service_date')->nullable();
            $table->string('product')->nullable();
            $table->string('product_id')->nullable();
            $table->decimal('gross_account_value', 10, 2)->nullable();
            $table->string('service_schedule')->nullable();
            $table->decimal('initial_service_cost', 10, 2)->nullable();
            $table->json('trigger_date')->nullable();
            $table->boolean('service_completed')->nullable();
            $table->integer('length_of_agreement')->nullable();
            $table->boolean('auto_pay')->nullable();
            $table->string('job_status')->nullable();
            $table->string('bill_status')->nullable();
            $table->decimal('subscription_payment', 10, 2)->nullable();
            $table->text('adders_description')->nullable();
            $table->timestamp('last_updated_date')->nullable();
            $table->timestamps(); // created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hometeam_json_raw_data');
    }
}
