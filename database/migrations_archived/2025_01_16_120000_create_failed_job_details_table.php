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
        Schema::create('failed_job_details', function (Blueprint $table) {
            $table->id();
            $table->string('failed_job_uuid')->index();
            $table->string('job_id')->nullable()->index()->comment('Original job UUID from payload');
            $table->string('job_class')->index()->comment('Job class name');
            $table->string('queue')->index()->comment('Queue name');
            $table->string('connection')->comment('Connection name');

            // Enhanced failure information
            $table->text('failure_reason')->nullable()->comment('Brief failure reason');
            $table->longText('stack_trace')->nullable()->comment('Full stack trace');
            $table->json('payload_data')->nullable()->comment('Job payload data');
            $table->json('context_data')->nullable()->comment('Additional context at failure');

            // System information at failure
            $table->decimal('memory_usage_mb', 10, 2)->nullable()->comment('Memory usage in MB');
            $table->decimal('peak_memory_mb', 10, 2)->nullable()->comment('Peak memory usage in MB');
            $table->integer('execution_time_ms')->nullable()->comment('Execution time in milliseconds');
            $table->string('worker_pid')->nullable()->comment('Worker process ID');
            $table->string('php_version')->nullable()->comment('PHP version');
            $table->text('server_info')->nullable()->comment('Server information');

            // Job attempt information
            $table->integer('attempts')->default(1)->comment('Number of attempts');
            $table->integer('max_tries')->nullable()->comment('Maximum tries configured');
            $table->integer('timeout')->nullable()->comment('Job timeout in seconds');
            $table->timestamp('first_failed_at')->nullable()->comment('When job first failed');
            $table->timestamp('last_failed_at')->nullable()->comment('When job last failed');

            // Error categorization
            $table->string('error_type')->nullable()->index()->comment('Type of error (db, timeout, exception, etc.)');
            $table->string('error_category')->nullable()->index()->comment('Error category for filtering');
            $table->boolean('is_retryable')->default(true)->comment('Whether job can be retried');
            $table->text('resolution_notes')->nullable()->comment('Notes on how to resolve');

            // Relationships
            $table->unsignedBigInteger('related_job_performance_log_id')->nullable()->index();

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('failed_job_uuid')
                ->references('uuid')
                ->on('failed_jobs')
                ->onDelete('cascade');

            $table->foreign('related_job_performance_log_id')
                ->references('id')
                ->on('job_performance_logs')
                ->onDelete('set null');

            // Indexes for performance
            $table->index(['job_class', 'error_type']);
            $table->index(['queue', 'error_category']);
            $table->index(['first_failed_at', 'error_type']);
            $table->index(['is_retryable', 'error_category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_job_details');
    }
};
