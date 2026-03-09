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
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('product');
            $table->string('product_code')->nullable()->after('product_id');
            $table->tinyInteger('is_exempted')->default(0)->comment('Default 0, 1 If exempted')->after('product_code');
            $table->double('total_commission_amount', 11, 2)->default(0)->after('is_exempted');
            $table->double('total_override_amount', 11, 2)->default(0)->after('total_commission_amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_masters', function (Blueprint $table) {
            //
        });
    }
};
