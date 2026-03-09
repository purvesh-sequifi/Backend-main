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
            if (! Schema::hasColumn('recon_override_history', 'payroll_execute_status')) {
                $table->string('payroll_execute_status', 10)->default(0);
            }
            if (! Schema::hasColumn('recon_override_history', 'pay_period_from')) {
                $table->date('pay_period_from')->nullable();
            }
            if (! Schema::hasColumn('recon_override_history', 'pay_period_to')) {
                $table->date('pay_period_to')->nullable();
            }
            if (! Schema::hasColumn('recon_override_history', 'payroll_id')) {
                $table->string('payroll_id', 10)->default(0);
            }
            if (! Schema::hasColumn('recon_override_history', 'is_next_payroll')) {
                $table->tinyInteger('is_next_payroll')->nullable();
            }
            if (! Schema::hasColumn('recon_override_history', 'is_mark_paid')) {
                $table->tinyInteger('is_mark_paid')->nullable();
            }
            if (! Schema::hasColumn('recon_override_history', 'is_displayed')) {
                $table->enum('is_displayed', ['0', '1'])->default('1')->comment('0 = Old, 1 = In Display');
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
