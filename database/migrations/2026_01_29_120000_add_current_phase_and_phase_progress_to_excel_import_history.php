<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds phase tracking so Import History can show "File uploading" 0-100% then "Sale processing" 0-100% in the same row, with "Completed" only after both phases.
     */
    public function up(): void
    {
        Schema::table('excel_import_history', function (Blueprint $table) {
            $table->string('current_phase', 32)->nullable()->after('status')->comment('sale_import | sale_processing | null when both done');
            $table->decimal('phase_progress', 5, 2)->nullable()->after('current_phase')->comment('0-100 for current phase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('excel_import_history', function (Blueprint $table) {
            $table->dropColumn(['current_phase', 'phase_progress']);
        });
    }
};
