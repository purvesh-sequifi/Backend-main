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
        Schema::create('bucket_subtask_by_job', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bucket_sutask_id'); // Foreign key column
            $table->foreign('bucket_sutask_id')->references('id')->on('bucket_subtask')->onDelete('cascade');
            $table->unsignedBigInteger('job_id'); // Foreign key column
            $table->foreign('job_id')->references('id')->on('crm_sale_info')->onDelete('cascade');
            $table->tinyInteger('status');
            $table->dateTime('date');
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
        Schema::dropIfExists('bucket_subtask_by_job');
    }
};
