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
        Schema::table('position_commission_upfronts', function (Blueprint $table) {
            $table->enum('deductible_from_prior', ['0', '1'])->default('0')->after('upfront_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('position_commission_upfronts', function (Blueprint $table) {
            $table->dropColumn('deductible_from_prior');
        });
    }
};
