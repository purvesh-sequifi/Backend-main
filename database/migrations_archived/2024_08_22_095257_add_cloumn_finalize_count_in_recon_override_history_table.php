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
        Schema::table('recon_override_history', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_override_history', 'finalize_count')) {
                $table->string('finalize_count')->nullable();
            }
        });
        Schema::table('recon_override_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_override_history_locks', 'finalize_count')) {
                $table->string('finalize_count')->nullable();
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
        Schema::table('recon_override_history', function (Blueprint $table) {
            //
        });
    }
};
