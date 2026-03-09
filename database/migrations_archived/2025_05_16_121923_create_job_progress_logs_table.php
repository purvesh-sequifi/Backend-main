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
        Schema::create('job_progress_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->index()->comment('UUID of the job');
            $table->string('job_class')->index()->comment('Class name of the job');
            $table->string('queue')->index()->comment('Queue the job is running on');
            $table->string('status')->index()->comment('Current status: queued, processing, completed, failed');
            $table->string('type')->nullable()->comment('Custom job type identifier (e.g., FR_officeName)');
            $table->integer('progress_percentage')->default(0)->comment('Progress percentage 0-100');
            $table->integer('total_records')->nullable()->comment('Total records to process');
            $table->integer('processed_records')->nullable()->comment('Number of records processed');
            $table->text('current_operation')->nullable()->comment('Current operation being performed');
            $table->text('last_record_identifier')->nullable()->comment('Identifier of the last processed record');
            $table->text('message')->nullable()->comment('Additional status message');
            $table->json('metadata')->nullable()->comment('Additional metadata as JSON');
            $table->json('error')->nullable()->comment('Error details if failed');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_progress_logs');
    }
};
