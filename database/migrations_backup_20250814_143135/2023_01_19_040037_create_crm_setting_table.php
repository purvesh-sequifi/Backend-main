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
        Schema::create('crm_setting', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('crm_id')->nullable();
            $table->integer('company_id')->nullable();
            $table->text('value')->nullable();
            $table->tinyInteger('status')->default('0');
            $table->timestamps();
            $table->foreign('crm_id')->references('id')
                ->on('crms')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crm_setting');
    }
};
