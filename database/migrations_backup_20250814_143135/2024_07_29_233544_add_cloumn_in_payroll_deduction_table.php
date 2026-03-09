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
            if (! Schema::hasColumn('payroll_deductions', 'is_move_to_recon_paid')) {
                $table->tinyInteger('is_move_to_recon_paid')->nullable()->default(0);
            }
        });
        Schema::table('payroll_deduction_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_deduction_locks', 'is_move_to_recon_paid')) {
                $table->tinyInteger('is_move_to_recon_paid')->nullable()->default(0);
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
        Schema::table('payroll_deductions', function (Blueprint $table) {
            //
        });
    }
};
