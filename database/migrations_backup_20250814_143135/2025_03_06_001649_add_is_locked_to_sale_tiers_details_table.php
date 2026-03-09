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
        Schema::table('sale_tiers_details', function (Blueprint $table) {
            $table->tinyInteger('is_locked')->comment('0 = Not Locked, 1 = Locked')->default('0')->after('sub_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_tiers_details', function (Blueprint $table) {
            //
        });
    }
};
