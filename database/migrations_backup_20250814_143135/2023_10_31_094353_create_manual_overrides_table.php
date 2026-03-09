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
        Schema::create('manual_overrides', function (Blueprint $table) {
            $table->id();
            $table->integer('manual_user_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->double('overrides_amount', 8, 2)->nullable();
            $table->enum('overrides_type', ['per sale', 'per kw']);
            $table->date('effective_date')->nullable();
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
        Schema::dropIfExists('manual_overrides');
    }
};
