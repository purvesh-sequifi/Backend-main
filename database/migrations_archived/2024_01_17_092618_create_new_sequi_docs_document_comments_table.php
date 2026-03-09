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
        Schema::create('new_sequi_docs_document_comments', function (Blueprint $table) {
            $table->bigIncrements('id')->autoIncrement();
            $table->integer('document_id');
            $table->integer('category_id')->nullable();
            $table->integer('template_id')->nullable();
            $table->string('document_name')->nullable()->comment('send document name like offer letter or other doc like W9');
            $table->enum('user_id_from', ['users', 'onboarding_employees'])->default('onboarding_employees');
            $table->enum('comment_user_id_from', ['users', 'onboarding_employees'])->default('users');
            $table->integer('document_send_to_user_id');
            $table->integer('comment_by_id');
            $table->string('comment_type')->nullable();
            $table->text('comment')->nullable();
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
        Schema::dropIfExists('new_sequi_docs_document_comments');
    }
};
