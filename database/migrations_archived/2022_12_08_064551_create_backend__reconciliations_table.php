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
        Schema::create('backend_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->date('day_date')->nullable();
            $table->unsignedBigInteger('backend_setting_id');
            $table->timestamps();

            $table->foreign('backend_setting_id')->references('id')
                ->on('backend_settings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('backend__reconciliations');
    }
};
