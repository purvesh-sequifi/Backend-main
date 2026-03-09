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
        Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliation_finalize_history', 'user_recon_is_skip')) {
                $table->tinyInteger('user_recon_is_skip')->nullable()->default(0);
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
        Schema::table('reconciliation_finalize_history', function (Blueprint $table) {
            //
        });
    }
};
