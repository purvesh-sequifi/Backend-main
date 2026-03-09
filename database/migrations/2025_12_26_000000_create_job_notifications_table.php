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
        Schema::create('job_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique()->index()->comment('Unique job identifier (UUID)');
            $table->string('job_type', 50)->index()->comment('Type of job (e.g., sales, payroll)');
            $table->string('job_name')->comment('Job class name');
            $table->string('status', 20)->index()->comment('current status: started, processing, completed, failed');
            $table->unsignedTinyInteger('progress')->default(0)->comment('Progress percentage (0-100)');
            $table->text('message')->nullable()->comment('Human-readable progress message');
            $table->json('metadata')->nullable()->comment('Additional data (record counts, file info, etc.)');
            
            // Multi-domain support
            $table->unsignedBigInteger('company_profile_id')->nullable()->index();
            $table->string('domain_name', 100)->nullable()->index();
            
            // User tracking
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('User who initiated the job');
            $table->string('session_key')->nullable()->index()->comment('Browser session/tab identifier');
            
            // Timing
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable()->comment('Actual duration in seconds');
            $table->unsignedInteger('estimated_duration_seconds')->nullable()->comment('Estimated duration');
            
            // Performance metrics
            $table->unsignedInteger('records_processed')->nullable();
            $table->decimal('records_per_second', 10, 2)->nullable();
            $table->decimal('memory_peak_mb', 10, 2)->nullable();
            $table->string('file_url')->nullable()->comment('Generated file URL for download');
            $table->decimal('file_size_kb', 10, 2)->nullable();
            
            // Error tracking
            $table->text('error_message')->nullable();
            $table->string('error_file')->nullable();
            $table->unsignedInteger('error_line')->nullable();
            
            // Soft delete for history
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['company_profile_id', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['session_key', 'status']);
            $table->index(['status', 'created_at']);
            
            // Composite index for common query patterns
            $table->index(['company_profile_id', 'user_id', 'status', 'created_at'], 'idx_company_user_status_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_notifications');
    }
};

