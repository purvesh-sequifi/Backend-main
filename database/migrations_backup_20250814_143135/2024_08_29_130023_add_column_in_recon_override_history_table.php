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
            if (! Schema::hasColumn('recon_override_history', 'move_from_payroll')) {
                $table->string('move_from_payroll')->nullable()->after('type');
            }
        });
        Schema::table('recon_override_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_override_history_locks', 'move_from_payroll')) {
                $table->string('move_from_payroll')->nullable()->after('type');
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
