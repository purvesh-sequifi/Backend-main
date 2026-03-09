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
        Schema::table('recon_commission_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_commission_histories', 'type')) {
                $table->string('type')->nullable()->after('end_date');
            }
            if (! Schema::hasColumn('recon_commission_histories', 'move_from_payroll')) {
                $table->string('move_from_payroll')->nullable()->after('type');
            }
        });
        Schema::table('recon_commission_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_commission_history_locks', 'type')) {
                $table->string('type')->nullable()->after('end_date');
            }
            if (! Schema::hasColumn('recon_commission_history_locks', 'move_from_payroll')) {
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
        Schema::table('recon_commission_histories', function (Blueprint $table) {
            //
        });
    }
};
