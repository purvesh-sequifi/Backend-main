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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->integer('created_by')->nullable();
            $table->unsignedBigInteger('categery_id')->nullable();
            $table->string('template_name')->nullable();
            $table->longText('template_description')->nullable();
            $table->smallInteger('is_sign_required_for_hire')->nullable()->default(1);
            $table->longText('template_content')->nullable();
            $table->string('template_agreements')->nullable();
            $table->longText('dynamic_value')->nullable();
            $table->integer('recipient_sign_req')->nullable();
            $table->integer('self_sign_req')->nullable();
            $table->integer('add_sign')->nullable();
            $table->longText('template_comment')->nullable();
            $table->integer('manager_sign_req')->default(0);
            $table->integer('completed_step')->default(0);
            $table->tinyInteger('recruiter_sign_req')->default(0);
            $table->tinyInteger('add_recruiter_sign_req')->default(0);
            $table->timestamps();

            // $table->foreign('categery_id')->references('id')
            // ->on('template_categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sequi_docs_templates');
    }
};
