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
        Schema::create('pipline_lead_status', function (Blueprint $table) {
            $table->id();
            $table->string('status_name');
            $table->tinyInteger('display_order');
            $table->tinyInteger('hide_status')->default(0);
            $table->string('colour_code')->default('#E4E9FF');
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
        Schema::dropIfExists('lead_status');
    }
};
