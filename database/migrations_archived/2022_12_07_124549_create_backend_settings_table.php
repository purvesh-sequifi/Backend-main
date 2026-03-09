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
        Schema::create('backend_settings', function (Blueprint $table) {
            $table->id();
            $table->double('commission_withheld', 8, 2)->nullable();
            $table->double('maximum_withheld', 8, 2)->nullable();
            $table->string('commission_type')->nullable();
            $table->integer('status')->nullable();
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
        Schema::dropIfExists('backend_settings');
    }
};
