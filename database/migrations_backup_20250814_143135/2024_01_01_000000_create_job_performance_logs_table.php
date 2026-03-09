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
        Schema::create('job_performance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->nullable();
            $table->string('job_class');
            $table->string('queue');
            $table->string('connection');
            $table->json('payload')->nullable();
            $table->enum('status', ['started', 'completed', 'failed'])->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->integer('processing_time_ms')->nullable(); // Processing time in milliseconds
            $table->integer('memory_usage_mb')->nullable(); // Memory usage in MB
            $table->integer('attempts')->default(1);
            $table->text('error_message')->nullable();
            $table->string('worker_pid')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['queue', 'status', 'created_at']);
            $table->index(['job_class', 'status', 'created_at']);
            $table->index(['started_at', 'completed_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('job_performance_logs');
    }
};
