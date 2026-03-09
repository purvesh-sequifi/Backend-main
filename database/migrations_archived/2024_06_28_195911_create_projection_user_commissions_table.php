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
        Schema::create('projection_user_commissions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('pid');
            $table->string('type')->comment('M1, M2');
            $table->float('amount', 11, 2)->default(0);
            $table->date('customer_signoff');
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
        Schema::dropIfExists('projection_user_commissions');
    }
};
