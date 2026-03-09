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
        // Schema::create('additional_custom_fields', function (Blueprint $table) {
        //     $table->id();
        //     $table->unsignedBigInteger('configuration_id');
        //     $table->string('field_name')->nullable();
        //     $table->string('field_type')->nullable();
        //     $table->string('field_required')->nullable();
        //     $table->string('type')->nullable();
        //     $table->text('attribute_option')->nullable();
        //     $table->integer('height_value')->nullable();
        //     $table->tinyInteger('is_deleted')->default('0');
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('additional_custom_fields');
    }
};
