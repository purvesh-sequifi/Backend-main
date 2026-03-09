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

        Schema::create('pipeline_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipeline_lead_status_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('comment_parent_id')->nullable();
            $table->text('comment')->nullable();
            $table->string('path')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pipeline_comments');
    }
};
