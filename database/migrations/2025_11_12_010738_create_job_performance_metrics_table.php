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
        Schema::create('job_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->unique()->index();
            $table->string('job_type')->index();
            $table->integer('total_pids')->default(0);
            $table->integer('total_chunks')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->integer('success_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->decimal('average_chunk_time', 8, 2)->nullable();
            $table->bigInteger('peak_memory_usage')->nullable();
            $table->string('queue_name')->index();
            $table->string('triggered_by')->nullable();
            $table->json('request_params')->nullable();
            $table->decimal('system_load_start', 5, 2)->nullable();
            $table->decimal('system_load_end', 5, 2)->nullable();
            $table->bigInteger('redis_ops_start')->nullable();
            $table->bigInteger('redis_ops_end')->nullable();
            $table->enum('status', ['started', 'processing', 'completed', 'failed'])->default('started')->index();
            $table->timestamps();

            // Indexes for performance
            $table->index(['job_type', 'status']);
            $table->index(['started_at', 'completed_at']);
            $table->index(['queue_name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_performance_metrics');
    }
};