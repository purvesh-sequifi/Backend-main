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
        Schema::table('job_progress_logs', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('completed_at')->index();
            // Add a field to track partial completion status
            $table->integer('completed_records')->nullable()->after('processed_records')
                ->comment('Number of records successfully processed before job ended');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_progress_logs', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
            $table->dropColumn('completed_records');
        });
    }
};
