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
        Schema::table('position_reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('position_reconciliations', 'tiers_commission_settlement')) {
                $table->string('tiers_commission_settlement', 255)->nullable()->after('maximum_withheld');
            }
            if (! Schema::hasColumn('position_reconciliations', 'tiers_override_settlement')) {
                $table->string('tiers_override_settlement', 255)->nullable()->after('tiers_commission_settlement');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position_reconciliations', function (Blueprint $table) {
            if (Schema::hasColumn('position_reconciliations', 'tiers_commission_settlement')) {
                $table->dropColumn('tiers_commission_settlement');
            }
            if (Schema::hasColumn('position_reconciliations', 'tiers_override_settlement')) {
                $table->dropColumn('tiers_override_settlement');
            }
        });
    }
};
