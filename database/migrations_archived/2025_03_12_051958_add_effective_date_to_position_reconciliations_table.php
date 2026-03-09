<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEffectiveDateToPositionReconciliationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasColumn('position_reconciliations', 'effective_date')) {
            Schema::table('position_reconciliations', function (Blueprint $table) {
                $table->date('effective_date')->nullable()->after('stack_settlement');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Check if the column exists before dropping it
        if (Schema::hasColumn('position_reconciliations', 'effective_date')) {
            Schema::table('position_reconciliations', function (Blueprint $table) {
                $table->dropColumn('effective_date');
            });
        }
    }
}
