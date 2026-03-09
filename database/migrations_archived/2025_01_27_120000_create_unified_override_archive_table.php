<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Unified Override Archive System V2
     */
    public function up(): void
    {
        // Create unified archive table for both normal and projection overrides
        Schema::create('override_archive', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->comment('Original override ID before deletion');
            $table->enum('override_type', ['normal', 'projection'])->comment('Type of override: normal or projection');
            
            // Common fields (both types)
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('sale_user_id')->nullable();
            $table->string('pid');
            $table->string('type')->comment('Override type: Direct, Indirect, Office, One Time, etc.');
            $table->decimal('kw', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('overrides_amount', 10, 2)->nullable();
            $table->enum('overrides_type', ['per sale', 'per kw', 'percent'])->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->string('overrides_settlement_type')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->unsignedBigInteger('office_id')->nullable();
            $table->decimal('calculated_redline', 10, 2)->nullable();
            $table->string('calculated_redline_type')->nullable();
            
            // Normal override specific fields (nullable for projection overrides)
            $table->unsignedBigInteger('payroll_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('during')->nullable();
            $table->string('product_code')->nullable();
            $table->decimal('net_epc', 10, 2)->nullable();
            $table->text('comment')->nullable();
            $table->decimal('adjustment_amount', 10, 2)->nullable();
            $table->tinyInteger('customer_signoff')->nullable();
            $table->tinyInteger('is_mark_paid')->nullable();
            $table->tinyInteger('is_next_payroll')->nullable();
            $table->tinyInteger('is_displayed')->nullable();
            $table->string('ref_id')->nullable();
            $table->tinyInteger('is_move_to_recon')->nullable();
            $table->tinyInteger('recon_status')->nullable();
            $table->tinyInteger('is_onetime_payment')->nullable();
            $table->unsignedBigInteger('one_time_payment_id')->nullable();
            $table->string('worker_type')->nullable();
            
            // Projection override specific fields (nullable for normal overrides)
            $table->string('customer_name')->nullable();
            $table->string('override_over')->nullable();
            $table->decimal('total_override', 10, 2)->nullable();
            $table->tinyInteger('is_stop_payroll')->nullable();
            $table->date('date')->nullable();
            
            // Archive-specific fields
            $table->timestamp('deleted_at')->useCurrent();
            $table->unsignedBigInteger('deleted_by');
            $table->string('deletion_reason')->nullable();
            $table->date('original_pay_period_from')->nullable()->comment('Pay period when originally created');
            $table->date('original_pay_period_to')->nullable()->comment('Pay period when originally created');
            $table->tinyInteger('can_restore')->default(1)->comment('Whether this override can be restored');
            $table->date('restoration_pay_period_from')->nullable()->comment('Pay period when restored');
            $table->date('restoration_pay_period_to')->nullable()->comment('Pay period when restored');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['pid', 'deleted_at'], 'idx_pid_deleted');
            $table->index(['deleted_by'], 'idx_deleted_by');
            $table->index(['original_id'], 'idx_original_id');
            $table->index(['user_id', 'type'], 'idx_user_type');
            $table->index(['sale_user_id'], 'idx_sale_user');
            $table->index(['override_type'], 'idx_override_type');
            $table->index(['can_restore', 'status'], 'idx_can_restore');
            $table->index(['pid', 'override_type'], 'idx_pid_override_type');
            
            // Foreign key constraints
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('sale_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('override_archive');
    }
};
