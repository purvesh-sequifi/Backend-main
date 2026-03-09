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
        Schema::table('recon_clawback_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_clawback_history_locks', 'finalize_id')) {
                $table->unsignedBigInteger('finalize_id')->after('user_id');
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
        Schema::table('recon_clawback_history_locks', function (Blueprint $table) {
            //
        });
    }
};
