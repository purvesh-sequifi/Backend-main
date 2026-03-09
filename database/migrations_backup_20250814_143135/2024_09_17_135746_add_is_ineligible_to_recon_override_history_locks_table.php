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
        Schema::table('recon_override_history_locks', function (Blueprint $table) {
            $table->tinyInteger('is_ineligible')->after('is_displayed')->default('0')->comment('0 = Eligible, 1 = Ineligible');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('recon_override_history_locks', function (Blueprint $table) {
            //
        });
    }
};
