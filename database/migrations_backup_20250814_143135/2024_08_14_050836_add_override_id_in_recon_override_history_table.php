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
            if (! Schema::hasColumn('recon_override_history', 'override_id')) {
                $table->string('override_id', 15)->nullable()->after('id');
            }
        });
        Schema::table('recon_override_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_override_history_locks', 'override_id')) {
                $table->string('override_id', 15)->nullable()->after('id');
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
