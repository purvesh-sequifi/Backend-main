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
        Schema::table('payroll_deductions', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_deductions', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });
        Schema::table('payroll_deduction_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_deduction_locks', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });

        Schema::table('payroll_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_adjustments', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });
        Schema::table('payroll_adjustments_lock', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_adjustments_lock', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });

        Schema::table('payroll_adjustment_details', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_adjustment_details', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            }
        });
        Schema::table('payroll_adjustments_lock', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_adjustments_lock', 'is_move_to_recon')) {
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {}
};
