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
        Schema::table('payroll_adjustment_details', function (Blueprint $table) {
            $table->string('adjustment_type')->nullable()->after('payroll_type');
            $table->string('sale_user_id')->nullable()->after('pid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payroll_adjustment_details', function (Blueprint $table) {
            //
        });
    }
};
