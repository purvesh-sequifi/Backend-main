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
        Schema::create('crmsale_custom_field', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->string('type', 25)->nullable();
            $table->text('value')->nullable();
            $table->integer('visiblecustomer');
            $table->integer('status');
            $table->integer('sort_order');
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
        Schema::dropIfExists('crmsale_custom_field');
    }
};
