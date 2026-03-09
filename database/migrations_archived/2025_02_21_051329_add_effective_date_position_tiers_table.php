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
        Schema::table('position_tiers', function (Blueprint $table) {
            if (! Schema::hasColumn('position_tiers', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position_tiers', function (Blueprint $table) {
            if (Schema::hasColumn('position_tiers', 'effective_date')) {
                $table->dropColumn('effective_date');
            }
        });
    }
};
