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
        // Schema::table('reconciliation_finalize_history_locks', function (Blueprint $table) {
        //     if(Schema::hasColumn("reconciliation_finalize_history_locks", "id")){
        //         $table->dropPrimary('id');
        //         // $table->dropColumn('id');

        //         // Modify the column to remove auto-increment
        //         // $table->integer('id')->nullable();
        //     }
        // });
        // Schema::table('recon_override_history_locks', function (Blueprint $table) {
        //     if(Schema::hasColumn("recon_override_history_locks", "id")){
        //         $table->dropPrimary('id');
        //         // $table->dropColumn('id');

        //         // Modify the column to remove auto-increment
        //         // $table->integer('id')->nullable();
        //     }
        // });
        // Schema::table('recon_commission_history_locks', function (Blueprint $table) {
        //     if (Schema::hasColumn('recon_commission_history_locks', 'id')) {

        //         // Drop the id column
        //         $table->dropColumn('id');
        //     }
        // });

        // Schema::table('reconciliation_finalize_history_locks', function (Blueprint $table) {
        //     $table->integer('id')->nullable();
        // });
        // Schema::table('recon_override_history_locks', function (Blueprint $table) {
        //     $table->integer('id')->nullable();
        // });
        // Schema::table('recon_commission_history_locks', function (Blueprint $table) {
        //     $table->integer('id')->nullable();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::table('recon_locks_tables', function (Blueprint $table) {
        //     //
        // });
    }
};
