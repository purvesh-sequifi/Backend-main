<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCommissionLimitAndEffectiveDatePositionCommissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('position_commissions', function (Blueprint $table) {
            // Adding new columns to the position_commissions table
            if (! Schema::hasColumn('position_commissions', 'commission_limit')) {
                $table->decimal('commission_limit', 15, 2)->nullable()->after('commission_parentag_type_hiring_locked');
            }

            if (! Schema::hasColumn('position_commissions', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('commission_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('position_commissions', function (Blueprint $table) {
            // Dropping the added columns in case of rollback
            $table->dropColumn(['commission_limit', 'effective_date', 'commission_parentag_type_hiring_locked']);
        });
    }
}
