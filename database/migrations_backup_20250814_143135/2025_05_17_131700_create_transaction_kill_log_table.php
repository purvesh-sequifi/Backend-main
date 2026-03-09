<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionKillLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_kill_log', function (Blueprint $table) {
            $table->id();
            $table->dateTime('log_time')->index();
            $table->unsignedBigInteger('thread_id')->nullable();
            $table->string('trx_id', 18)->nullable();
            $table->string('user', 256)->nullable();
            $table->string('host', 256)->nullable();
            $table->string('db', 64)->nullable();
            $table->string('command', 64)->nullable();
            $table->string('state', 256)->nullable();
            $table->integer('seconds_open')->nullable();
            $table->integer('rows_modified')->nullable();
            $table->text('error_message')->nullable();
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
        Schema::dropIfExists('transaction_kill_log');
    }
}
