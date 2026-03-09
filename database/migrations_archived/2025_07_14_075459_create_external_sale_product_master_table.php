<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExternalSaleProductMasterTable extends Migration
{
    public function up()
    {
        Schema::create('external_sale_product_master', function (Blueprint $table) {
            $table->id();

            $table->string('pid');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('milestone_id');
            $table->unsignedBigInteger('milestone_schema_id');
            $table->date('milestone_date')->nullable();

            $table->string('type')->nullable();

            $table->tinyInteger('is_last_date')->default(0)->comment('0=No, 1=Last milestone date');
            $table->tinyInteger('is_exempted')->default(0)->comment('0=Not Exempted, 1=Exempted');
            $table->tinyInteger('is_override')->default(0)->comment('0=No, 1=Override');
            $table->tinyInteger('is_projected')->default(1)->comment('0=Non Projected, 1=Projected');
            $table->tinyInteger('is_paid')->default(0)->comment('0=Not Paid, 1=Paid');

            $table->unsignedBigInteger('worker_id')->nullable();
            $table->tinyInteger('worker_type')->nullable()->comment('1=selfgen, 2=closer, 3=setter');

            $table->double('amount', 11, 2)->default(0.00);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('external_sale_product_master');
    }
}
