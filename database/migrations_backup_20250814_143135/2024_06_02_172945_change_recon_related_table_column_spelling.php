<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('move_to_reconciliations')) {
            Schema::table('move_to_reconciliations', function (Blueprint $table) {
                if (Schema::hasColumn('move_to_reconciliations', 'payroll')) {
                    $table->renameColumn('payroll', 'payroll_id');
                }
            });
        }

        if (Schema::hasTable('reconciliation_finalize_history')) {
            Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
                // Rename the existing id column to temp_id
                if (Schema::hasColumn('reconciliation_finalize_history', 'temp_id')) {
                    $table->renameColumn('temp_id', 'id');
                } else {
                    $table->dropColumn('id');
                }
            });
            Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
                // Add a new auto-incrementing id column
                $table->increments('id')->after('user_id');
            });

            // Optionally, you can copy the data from temp_id to the new id column if necessary
            // DB::statement('UPDATE reconciliation_finalize_history SET id = temp_id');

            Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
                // Drop the temporary column
                if (Schema::hasColumn('reconciliation_finalize_history', 'temp_id')) {
                    $table->dropColumn('temp_id');
                }
            });
        }
        /* if (Schema::hasTable('position_commissions')) {
            Schema::table('position_commissions', function (Blueprint $table) {
                if (Schema::hasColumn('position_commissions', 'commission_parentag_hiring_locked')) {
                    $table->renameColumn('commission_parentag_hiring_locked', 'commission_percentage_hiring_locked');
                }
                if (Schema::hasColumn('position_commissions', 'commission_parentag_type_hiring_locked')) {
                    $table->renameColumn('commission_parentag_type_hiring_locked', 'commission_percentage_type_hiring_locked');
                }
            });
        }
        if (Schema::hasTable('position_commission_upfronts')) {
            Schema::table('position_commission_upfronts', function (Blueprint $table) {
                if (Schema::hasColumn('position_commission_upfronts', 'upfront_ammount')) {
                    $table->renameColumn('upfront_ammount', 'upfront_amount');
                }
                if (Schema::hasColumn('position_commission_upfronts', 'upfront_ammount_locked')) {
                    $table->renameColumn('upfront_ammount_locked', 'upfront_amount_locked');
                }
            });
            Schema::rename('position_commission_upfronts', 'position_commission_upfront');
        } */
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('move_to_reconciliations')) {
            Schema::table('move_to_reconciliations', function (Blueprint $table) {
                if (Schema::hasColumn('move_to_reconciliations', 'payroll')) {
                    $table->renameColumn('payroll', 'payroll_id');
                }
            });
        }
        /* if (Schema::hasTable('position_commissions')) {
            Schema::table('position_commissions', function (Blueprint $table) {
                if (Schema::hasColumn('position_commissions', 'commission_percentage_hiring_locked')) {
                    $table->renameColumn('commission_percentage_hiring_locked', 'commission_parentag_hiring_locked');
                }
                if (Schema::hasColumn('position_commissions', 'commission_percentage_type_hiring_locked')) {
                    $table->renameColumn('commission_percentage_type_hiring_locked', 'commission_parentag_type_hiring_locked');
                }
            });
        } */
    }
};
