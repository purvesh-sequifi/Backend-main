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
            $table->unsignedBigInteger('payroll_type_id')->nullable()->after('sale_user_id');
        });
        Schema::table('payroll_adjustment_details_lock', function (Blueprint $table) {
            $table->unsignedBigInteger('payroll_type_id')->nullable()->after('sale_user_id');
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
