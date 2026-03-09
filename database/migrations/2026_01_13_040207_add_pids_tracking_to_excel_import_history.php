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
        Schema::table('excel_import_history', function (Blueprint $table) {
            $table->json('updated_pids')->nullable()->after('updated_records')->comment('Array of PIDs that were updated');
            $table->json('new_pids')->nullable()->after('new_records')->comment('Array of PIDs that were created');
            $table->json('error_pids')->nullable()->after('error_records')->comment('Array of PIDs that had errors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('excel_import_history', function (Blueprint $table) {
            $table->dropColumn(['updated_pids', 'new_pids', 'error_pids']);
        });
    }
};
