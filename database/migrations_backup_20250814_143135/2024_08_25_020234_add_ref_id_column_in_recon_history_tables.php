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
            if (! Schema::hasColumn('recon_commission_histories', 'ref_id')) {
                $table->bigInteger('ref_id')->nullable()->after('is_next_payroll');
            }
        });
        Schema::table('recon_commission_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_commission_history_locks', 'ref_id')) {
                $table->bigInteger('ref_id')->nullable()->after('is_next_payroll');
            }
        });

        Schema::table('recon_override_history', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_override_history', 'ref_id')) {
                $table->bigInteger('ref_id')->nullable()->after('is_next_payroll');
            }
        });
        Schema::table('recon_override_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_override_history_locks', 'ref_id')) {
                $table->bigInteger('ref_id')->nullable()->after('is_next_payroll');
            }
        });
        Schema::table('recon_override_history', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_override_history', 'is_displayed')) {
                $table->tinyInteger('is_displayed')->default(1)->nullable()->after('is_next_payroll');
            }
        });
        Schema::table('recon_override_history_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_override_history_locks', 'is_displayed')) {
                $table->tinyInteger('is_displayed')->default(1)->nullable()->after('is_next_payroll');
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
        Schema::table('recon_history_tables', function (Blueprint $table) {
            //
        });
    }
};
