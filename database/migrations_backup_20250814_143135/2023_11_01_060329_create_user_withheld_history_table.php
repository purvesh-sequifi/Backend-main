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
        Schema::create('user_withheld_history', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->integer('updater_id')->nullable();
            $table->decimal('withheld_amount', 8, 2)->nullable();
            $table->decimal('old_withheld_amount', 8, 2)->nullable();
            $table->enum('withheld_type', ['per sale', 'per kw'])->nullable();
            $table->enum('old_withheld_type', ['per sale', 'per kw'])->nullable();
            $table->date('withheld_effective_date')->nullable();
            $table->integer('position_id')->nullable();
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
        Schema::dropIfExists('user_withheld_history');
    }
};
