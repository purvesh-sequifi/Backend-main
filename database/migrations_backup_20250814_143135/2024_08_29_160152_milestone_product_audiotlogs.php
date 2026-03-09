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
        Schema::create('milestone_product_audiotlogs', function (Blueprint $table) {
            $table->id();
            $table->integer('reference_id');
            $table->string('type');
            $table->string('event');
            $table->text('description');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Foreign key to milestone_schemas
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
        Schema::dropIfExists('milestone_product_audiotlogs');
    }
};
