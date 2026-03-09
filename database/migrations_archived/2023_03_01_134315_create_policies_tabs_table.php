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
        Schema::create('policies_tabs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policies_id');
            $table->string('tabs')->nullable();
            $table->timestamps();
            // $table->foreign('policies_id')->references('id')
            // ->on('group_policies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('policies_tabs');
    }
};
