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
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 8, 2)->default(0);
            $table->decimal('old_balance_credit', 8, 2)->default(0);
            $table->decimal('used_credit', 8, 2)->default(0);
            $table->decimal('balance_credit', 8, 2)->default(0);
            $table->date('month')->nullable();
            $table->integer('use_status')->nullable();
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
        Schema::dropIfExists('credits');
    }
};
