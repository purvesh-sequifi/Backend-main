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
        Schema::create('everee_transactions_log', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('api_name', 100)->nullable();
            $table->text('payload');
            $table->text('response');
            $table->string('api_url', 150)->nullable();
            $table->timestamps(); // Creates created_at and updated_at columns
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('everee_transactions_log');
    }
};
