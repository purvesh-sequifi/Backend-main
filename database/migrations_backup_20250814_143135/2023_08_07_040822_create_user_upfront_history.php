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
        Schema::create('user_upfront_history', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('updater_id');
            $table->double('upfront_pay_amount', 8, 2)->nullable();
            $table->enum('upfront_sale_type', ['per sale', 'per kw']);
            $table->double('old_upfront_pay_amount', 8, 2)->nullable();
            $table->enum('old_upfront_sale_type', ['per sale', 'per kw']);
            $table->date('upfront_effective_date');
            $table->integer('position_id');
            $table->integer('sub_position_id')->nullable();
            $table->tinyInteger('self_gen_user')->nullable();
            $table->tinyInteger('old_self_gen_user')->nullable();
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
        Schema::dropIfExists('user_upfront_history');
    }
};
