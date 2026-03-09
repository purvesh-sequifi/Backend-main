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
        if (!Schema::hasTable('user_commission_audit_history')) {
            return; // Table will be created by later migration with these columns included
        }

        Schema::table('user_commission_audit_history', function (Blueprint $table) {
            if (!Schema::hasColumn('user_commission_audit_history', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('position_name')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_commission_audit_history', function (Blueprint $table) {
            $table->dropColumn('effective_date');
        });
    }
};
