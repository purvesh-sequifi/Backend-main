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
        Schema::create('override_system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('allow_manual_override_status')->nullable();
            $table->string('allow_office_stack_override_status')->nullable();
            $table->enum('pay_type', ['pay all overrides', 'pay override with the highest value'])->nullable();
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
        Schema::dropIfExists('override_system_settings');
    }
};
