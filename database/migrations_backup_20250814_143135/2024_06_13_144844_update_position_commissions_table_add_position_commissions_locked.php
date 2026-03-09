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
            // Add the position_commissions_locked column with comment
            $table->boolean('position_commissions_locked')
                ->default(1)
                ->comment('1 for locked, 0 for unlocked');
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
            // Remove the position_commissions_locked column
            $table->dropColumn('position_commissions_locked');
        });
    }
};
