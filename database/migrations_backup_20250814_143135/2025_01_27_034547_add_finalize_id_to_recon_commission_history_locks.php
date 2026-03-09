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
        Schema::table('recon_commission_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_commission_history_locks', 'finalize_id')) {
                $table->unsignedBigInteger('finalize_id')->after('user_id');
            }
            if (! Schema::hasColumn('recon_commission_history_locks', 'is_deducted')) {
                $table->tinyInteger('is_deducted')->default(0)->after('is_ineligible');
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
        Schema::table('recon_commission_history_locks', function (Blueprint $table) {
            //
        });
    }
};
