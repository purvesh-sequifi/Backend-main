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
        Schema::table('custom_field_history', function (Blueprint $table) {
            if (! Schema::hasColumn('custom_field_history', 'pay_period_from')) {
                $table->date('pay_period_from')->nullable()->after('is_next_payroll');
            }
            if (! Schema::hasColumn('custom_field_history', 'pay_period_to')) {
                $table->date('pay_period_to')->nullable()->after('pay_period_from');
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
        Schema::table('to_custom_field_history', function (Blueprint $table) {
            //
        });
    }
};
