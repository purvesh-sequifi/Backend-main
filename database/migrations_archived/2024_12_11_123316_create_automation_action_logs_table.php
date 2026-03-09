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
        Schema::create('automation_action_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('automation_rule_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('sub_task_id')->nullable();
            $table->unsignedBigInteger('old_pipeline_lead_status')->nullable();
            $table->unsignedBigInteger('new_pipeline_lead_status')->nullable();
            $table->string('event')->nullable();
            $table->string('category')->nullable();
            // $table->string('delay_in')->nullable();
            // $table->string('delay')->nullable();
            $table->integer('status')->default(0); // 0 => automationn not run yet
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
        Schema::dropIfExists('automation_action_logs');
    }
};
