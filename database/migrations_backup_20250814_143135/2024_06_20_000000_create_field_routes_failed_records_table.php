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
        Schema::create('field_routes_failed_records', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_id')->nullable();
            $table->string('customer_id')->nullable();
            $table->json('raw_data')->nullable();
            $table->string('failure_reason')->nullable();
            $table->text('failure_description')->nullable();
            $table->string('failure_type')->nullable(); // e.g., 'validation', 'transformation', 'business_rule'
            $table->timestamp('failed_at');
            $table->timestamps();

            // Add index for faster lookups
            $table->index(['subscription_id', 'customer_id']);
            $table->index('failure_type');
            $table->index('failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_routes_failed_records');
    }
};
