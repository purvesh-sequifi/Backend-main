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
        Schema::create('weekly_pay_frequencies', function (Blueprint $table) {
            $table->id();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->integer('closed_status')->nullable()->default(0);
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
        Schema::dropIfExists('weekly_pay_frequencies');
    }
};
