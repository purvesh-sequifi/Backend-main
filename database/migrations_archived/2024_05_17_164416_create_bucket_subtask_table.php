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
        Schema::create('bucket_subtask', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bucket_id'); // Foreign key column
            $table->foreign('bucket_id')->references('id')->on('buckets')->onDelete('cascade');
            $table->string('name', 50);
            $table->integer('created_id');
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
        Schema::dropIfExists('bucket_subtask');
    }
};
