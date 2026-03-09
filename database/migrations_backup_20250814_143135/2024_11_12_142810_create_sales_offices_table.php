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
        Schema::create('sales_offices', function (Blueprint $table) {
            $table->id();
            $table->string('office_name')->nullable();
            $table->integer('state_id')->nullable();
            $table->string('state_name')->nullable();
            $table->tinyInteger('status')->unsigned();
            $table->date('effective_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sales_offices');
    }
};
