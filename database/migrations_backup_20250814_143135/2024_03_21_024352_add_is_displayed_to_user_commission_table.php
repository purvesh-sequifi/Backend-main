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
        // Schema::table('user_commission', function (Blueprint $table) {
        //     $table->enum('is_displayed', ['0', '1'])->default('1')->after('is_stop_payroll')->comment('0 = Old, 1 = In Display');
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::table('user_commission', function (Blueprint $table) {
        //     //
        // });
    }
};
