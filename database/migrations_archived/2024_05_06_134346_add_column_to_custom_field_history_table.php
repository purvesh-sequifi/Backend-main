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
            // $table->date('pay_period_to')->nullable()->after('is_next_payroll');
            // $table->date('pay_period_from')->nullable()->after('is_next_payroll');
            // $table->string('comment')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('custom_field_history', function (Blueprint $table) {
            //
        });
    }
};
