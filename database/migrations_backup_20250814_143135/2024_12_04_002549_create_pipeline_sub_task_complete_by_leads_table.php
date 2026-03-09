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
        Schema::create('pipeline_sub_task_complete_by_leads', function (Blueprint $table) {
            $table->id();
            $table->integer('lead_id'); // Reference to PipelineSubTask
            $table->integer('pipeline_sub_task_id'); // Reference to PipelineSubTask
            $table->enum('completed', [0, 1])->default(0); // 0 = incomplete
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
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
        Schema::dropIfExists('pipeline_sub_task_complete_by_leads');
    }
};
