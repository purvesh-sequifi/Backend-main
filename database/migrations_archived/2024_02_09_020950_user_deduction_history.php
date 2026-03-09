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
        Schema::create('user_deduction_history', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->integer('updater_id')->nullable();
            $table->integer('cost_center_id')->nullable();
            $table->decimal('amount_par_paycheque', 8, 2)->nullable();
            $table->decimal('old_amount_par_paycheque', 8, 2)->nullable();
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
        Schema::dropIfExists('user_deduction_history');
    }
};
