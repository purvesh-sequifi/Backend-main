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
        Schema::table('user_commission', function (Blueprint $table) {
            if (! Schema::hasColumn('user_commission', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });

        Schema::table('user_commission_lock', function (Blueprint $table) {
            if (! Schema::hasColumn('user_commission_lock', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });

        Schema::table('payroll_adjustment_details', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_adjustment_details', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });

        Schema::table('payroll_adjustment_details_lock', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });

        Schema::table('clawback_settlements', function (Blueprint $table) {
            if (! Schema::hasColumn('clawback_settlements', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });

        Schema::table('clawback_settlements_lock', function (Blueprint $table) {
            if (! Schema::hasColumn('clawback_settlements_lock', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });

        Schema::table('user_overrides', function (Blueprint $table) {
            if (! Schema::hasColumn('user_overrides', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });

        Schema::table('user_overrides_lock', function (Blueprint $table) {
            if (! Schema::hasColumn('user_overrides_lock', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
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
        Schema::table('recon_to_payrolls_related', function (Blueprint $table) {
            //
        });
    }
};
