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
        Schema::create('job_chunk_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->index();
            $table->integer('chunk_number');
            $table->json('pids');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('duration_seconds', 8, 3)->nullable();
            $table->integer('success_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->bigInteger('memory_usage')->nullable();
            $table->decimal('cpu_usage', 5, 2)->nullable();
            $table->json('error_details')->nullable();
            $table->enum('status', ['started', 'processing', 'completed', 'failed'])->default('started');
            $table->timestamps();

            // Indexes for performance
            $table->index(['batch_id', 'chunk_number']);
            $table->index(['batch_id', 'status']);
            $table->index(['started_at', 'completed_at']);

            // Foreign key constraint
            $table->foreign('batch_id')->references('batch_id')->on('job_performance_metrics')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_chunk_metrics');
    }
};