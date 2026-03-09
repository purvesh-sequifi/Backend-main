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
        Schema::create('hiring_status', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->integer('display_order')->nullable()->default(0);
            $table->tinyInteger('hide_status')->nullable()->default(0);
            $table->string('colour_code')->nullable()->default('#E4E9FF');
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
        // Schema::dropIfExists('hiring_status');
    }
};
