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
        Schema::create('additional_pay_frequencies', function (Blueprint $table) {
            $table->id();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->tinyInteger('closed_status')->default('0')->comment('0 = Open, 1 = Closed');
            $table->tinyInteger('type')->comment('1 = Semi Weekly, 2 = Bi Monthly');
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
        Schema::dropIfExists('additional_pay_frequencies');
    }
};
