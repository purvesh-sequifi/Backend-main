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
        Schema::create('buckets', function (Blueprint $table) {
            $table->id();
            $table->string('bucket_type', 25);
            $table->string('name', 50);
            $table->tinyInteger('display_order');
            $table->tinyInteger('hide_status');
            $table->string('colour_code', 15);
            $table->integer('warning_day');
            $table->integer('danger_day');
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
        Schema::dropIfExists('buckets');
    }
};
