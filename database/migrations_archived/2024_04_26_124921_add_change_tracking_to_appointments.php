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
        // Only modify table if it exists
        if (Schema::hasTable('FieldRoutes_Appointment_Data')) {
            Schema::table('FieldRoutes_Appointment_Data', function (Blueprint $table) {
                // Add JSON column to track field changes
                $table->json('field_changes')->nullable()->after('last_modified')
                    ->comment('JSON object tracking when different field groups were last changed');

                // Add index for date_updated_fr for efficient filtering
                $table->index('date_updated_fr', 'idx_appointment_date_updated');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only modify table if it exists
        if (Schema::hasTable('FieldRoutes_Appointment_Data')) {
            Schema::table('FieldRoutes_Appointment_Data', function (Blueprint $table) {
                $table->dropColumn('field_changes');
                $table->dropIndex('idx_appointment_date_updated');
            });
        }
    }
};
