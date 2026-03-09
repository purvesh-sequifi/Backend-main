<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExternalSaleWorkerTable extends Migration
{
    public function up()
    {
        Schema::create('external_sale_worker', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('user_id'); // Refers to the worker (internal or external)
            $table->string('pid'); // Possibly refers to a product or sale ID
            $table->unsignedTinyInteger('type')->comment('1=selfgen, 2=closer, 3=setter');
            $table->timestamps(); // created_at and updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('external_sale_worker');
    }
}
