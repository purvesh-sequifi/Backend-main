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
        Schema::table('company_profiles', function (Blueprint $table) {
            // Add setup status tracking columns
            $table->enum('setup_status', ['pending', 'seeding', 'completed', 'failed'])
                  ->default('pending')
                  ->after('company_type')
                  ->index()
                  ->comment('Track company setup/seeding status');
            
            $table->timestamp('setup_completed_at')
                  ->nullable()
                  ->after('setup_status')
                  ->comment('Timestamp when setup was completed');
            
            $table->text('setup_error')
                  ->nullable()
                  ->after('setup_completed_at')
                  ->comment('Error message if setup failed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn(['setup_status', 'setup_completed_at', 'setup_error']);
        });
    }
};
