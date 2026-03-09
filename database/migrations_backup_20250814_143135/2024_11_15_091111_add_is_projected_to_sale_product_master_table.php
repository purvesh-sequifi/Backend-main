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
            $table->tinyInteger('is_projected')->default(1)->comment('0 = Non Projected, 1 = Projected')->after('is_exempted');
            $table->tinyInteger('is_paid')->default(0)->comment('0 = Non Paid, 1 = Paid')->after('is_projected');
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
