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
        Schema::table('position_commission_upfronts', function (Blueprint $table) {
            if (! Schema::hasColumn('position_commission_upfronts', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('upfront_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position_commission_upfronts', function (Blueprint $table) {
            if (Schema::hasColumn('position_commission_upfronts', 'effective_date')) {
                $table->dropColumn('effective_date');
            }
        });
    }
};
