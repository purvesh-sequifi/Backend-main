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
        Schema::create('setter_identify_alert', function (Blueprint $table) {
            $table->id();
            $table->string('pid')->nullable();
            $table->string('sales_rep_email')->nullable();
            $table->Integer('setter_id')->nullable();
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
        Schema::dropIfExists('setter_identify_alert');
    }
};
