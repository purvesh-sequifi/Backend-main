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
        Schema::create('bucket_by_job', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bucket_id'); // Foreign key column
            $table->foreign('bucket_id')->references('id')->on('buckets')->onDelete('cascade');
            $table->unsignedBigInteger('job_id'); // Foreign key column
            $table->foreign('job_id')->references('id')->on('crm_sale_info')->onDelete('cascade');
            $table->tinyInteger('active');
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
        Schema::dropIfExists('bucket_by_job');
    }
};
