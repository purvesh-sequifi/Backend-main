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
        Schema::create('user_commission_history', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('updater_id');
            $table->double('commission', 8, 2)->nullable();
            $table->enum('commission_type', ['percent', 'per kw'])->nullable();
            $table->enum('old_commission_type', ['percent', 'per kw'])->nullable();
            $table->date('commission_effective_date');
            $table->double('old_commission', 8, 2)->nullable();
            // $table->date('old_commission_effective_date');
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
        Schema::dropIfExists('user_commission_history');
    }
};
