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
        Schema::table('sale_product_master', function (Blueprint $table) {
            $table->string('type')->nullable()->after('milestone_date');
            $table->tinyInteger('is_exempted')->default(0)->comment('Default 0, 1 If exempted')->after('is_last_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_product_master', function (Blueprint $table) {
            //
        });
    }
};
