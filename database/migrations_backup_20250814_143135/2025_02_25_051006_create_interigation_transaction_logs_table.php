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
        Schema::create('interigation_transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->string('interigation_name')->nullable();
            $table->string('api_name')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->string('url')->nullable();
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
        Schema::dropIfExists('interigation_transaction_logs');
    }
};
