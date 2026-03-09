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
        Schema::create('upfront_system_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('upfront_for_self_gen', ['Pay highest value', 'Pay sum of setter and closer upfront'])->default('Pay highest value')->nullable();
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
        Schema::dropIfExists('upfront_system_settings');
    }
};
