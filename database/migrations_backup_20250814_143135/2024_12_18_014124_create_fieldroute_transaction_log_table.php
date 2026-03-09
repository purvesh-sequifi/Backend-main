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
        Schema::create('fieldroute_transaction_log', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('ticket_id')->nullable();
            $table->string('api_name', 100)->nullable();
            $table->text('payload');
            $table->longText('response');
            $table->string('api_url', 150)->nullable();
            $table->integer('is_processed')->default(0)->nullable();
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
        Schema::dropIfExists('fieldroute_transaction_log');
    }
};
