<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsOverrideToSaleProductMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sale_product_master', function (Blueprint $table) {
            $table->boolean('is_override')->default(0)->after('is_exempted');
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
            // $table->dropColumn('is_override');
        });
    }
}
