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
        Schema::table('tiers_position_commisions', function (Blueprint $table) {
            if (Schema::hasColumn('tiers_position_commisions', 'to_dealer_fee')) {
                $table->dropColumn('to_dealer_fee');
            }
            if (Schema::hasColumn('tiers_position_commisions', 'to_value')) {
                $table->dropColumn('to_value');
            }

            if (Schema::hasColumn('tiers_position_commisions', 'from_dealer_fee')) {
                $table->dropColumn('from_dealer_fee');
            }

            if (Schema::hasColumn('tiers_position_commisions', 'from_value')) {
                $table->dropColumn('from_value');
            }

            if (! Schema::hasColumn('tiers_position_commisions', 'commission_type')) {
                $table->string('commission_type', 255)->nullable()->after('commission_value');
            }

            if (! Schema::hasColumn('tiers_position_commisions', 'tiers_advancement')) {
                $table->string('tiers_advancement', 255)->nullable()->after('commission_type');
            }

            if (! Schema::hasColumn('tiers_position_commisions', 'commission_limit')) {
                if (Schema::hasColumn('tiers_position_commisions', 'commission_parentag_type_hiring_locked')) {
                    $table->decimal('commission_limit', 10, 2)->nullable()->after('commission_parentag_type_hiring_locked');
                } else {
                    $table->decimal('commission_limit', 10, 2)->nullable();
                }
            }

            if (! Schema::hasColumn('tiers_position_commisions', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('changes_field');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
