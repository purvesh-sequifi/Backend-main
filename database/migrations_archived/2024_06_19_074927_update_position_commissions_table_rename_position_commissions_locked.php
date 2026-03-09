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
        Schema::table('position_commissions', function (Blueprint $table) {
            $table->renameColumn('position_commissions_locked', 'commission_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Rename back the commission_status column to position_commissions_locked
        Schema::table('position_commissions', function (Blueprint $table) {
            $table->renameColumn('commission_status', 'position_commissions_locked');
        });
    }
};
