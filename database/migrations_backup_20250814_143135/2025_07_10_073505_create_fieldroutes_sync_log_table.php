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
        Schema::create('fieldroutes_sync_log', function (Blueprint $table) {
            $table->id();

            // Execution details
            $table->timestamp('execution_timestamp');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('command_parameters', 500)->nullable();

            // Office information
            $table->unsignedBigInteger('office_id')->nullable();
            $table->string('office_name');
            $table->integer('reps_processed')->default(0);

            // High-level metrics
            $table->integer('total_available')->default(0);
            $table->integer('subscriptions_fetched')->default(0);
            $table->integer('records_not_fetched')->default(0);

            // Records Created
            $table->integer('subscriptions_created')->default(0);
            $table->integer('customers_created')->default(0);
            $table->integer('appointments_created')->default(0);

            // Records Updated
            $table->integer('subscriptions_updated')->default(0);
            $table->integer('customers_updated')->default(0);
            $table->integer('appointments_updated')->default(0);

            // Customer Update Details
            $table->integer('customer_personal_changes')->default(0);
            $table->integer('customer_address_changes')->default(0);
            $table->integer('customer_status_changes')->default(0);
            $table->integer('customer_financial_changes')->default(0);

            // Appointment Update Details
            $table->integer('appointment_status_changes')->default(0);
            $table->integer('appointment_schedule_changes')->default(0);
            $table->integer('appointment_identifier_changes')->default(0);

            // Additional tracking
            $table->integer('records_touched')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->integer('customers_touched')->default(0);
            $table->integer('customers_skipped')->default(0);
            $table->integer('appointments_touched')->default(0);
            $table->integer('appointments_skipped')->default(0);
            $table->integer('errors')->default(0);

            // Processing metrics
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->boolean('is_dry_run')->default(false);
            $table->text('error_details')->nullable();

            $table->timestamps();

            // Indexes for better query performance
            $table->index(['execution_timestamp', 'office_id']);
            $table->index(['start_date', 'end_date']);
            $table->index('office_name');

            // Foreign key constraint
            $table->foreign('office_id')->references('id')->on('integrations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fieldroutes_sync_log');
    }
};
