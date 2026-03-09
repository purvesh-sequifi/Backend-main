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
        Schema::table('recon_clawback_histories', function (Blueprint $table) {
            $table->integer('sale_user_id')->nullable();
            $table->string('adders_type')->default('m2')->nullable()->comment('override, and clawback types')->after('sale_user_id')->nullable();
            $table->string('during')->default('m2')->nullable()->comment('m2, m2 update')->after('adders_type')->nullable();
        });

        Schema::table('recon_override_history', function (Blueprint $table) {
            $table->string('during')->default('m2')->nullable()->comment('override types')->after('type')->nullable();
        });

        Schema::table('recon_commission_histories', function (Blueprint $table) {
            $table->string('during')->default('m2')->nullable()->comment('m2, m2 update')->after('type')->nullable();
        });

        Schema::table('recon_clawback_history_locks', function (Blueprint $table) {
            $table->integer('sale_user_id')->nullable();
            $table->string('adders_type')->default('m2')->nullable()->comment('override, and clawback types')->after('sale_user_id')->nullable();
            $table->string('during')->default('m2')->nullable()->comment('m2, m2 update')->after('adders_type')->nullable();
        });

        Schema::table('recon_override_history_locks', function (Blueprint $table) {
            $table->string('during')->default('m2')->nullable()->comment('override types')->after('type')->nullable();
        });

        Schema::table('recon_commission_history_locks', function (Blueprint $table) {
            $table->string('during')->default('m2')->nullable()->comment('m2, m2 update')->after('type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
