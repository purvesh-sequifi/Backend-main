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
        Schema::create('monthly_pay_frequencies', function (Blueprint $table) {
            $table->id();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->string('closed_status')->nullable();
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
        Schema::dropIfExists('monthly_pay_frequencies');
    }
};
