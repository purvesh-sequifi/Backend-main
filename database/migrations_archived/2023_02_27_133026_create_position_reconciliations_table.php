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
        Schema::create('position_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position_id');
            $table->float('commission_withheld')->nullable();
            $table->enum('commission_type', ['per kw', 'per sale'])->nullable();
            $table->integer('commission_withheld_locked')->nullable();
            $table->integer('commission_type_locked')->nullable();
            $table->float('maximum_withheld')->nullable();
            $table->string('override_settlement')->nullable();
            $table->string('clawback_settlement')->nullable();
            $table->string('stack_settlement')->nullable();
            $table->tinyInteger('status')->default('1');
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
        Schema::dropIfExists('position_reconciliations');
    }
};
