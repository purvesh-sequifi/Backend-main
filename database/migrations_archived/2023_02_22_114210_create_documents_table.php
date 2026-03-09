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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('user_id_from', ['users', 'onboarding_employees'])->default('users');
            $table->integer('send_by')->nullable()->comment('Document send by user id');
            $table->date('document_send_date')->nullable();
            $table->integer('is_active')->nullable()->comment('0 not active , 1 for active doc')->default(1);
            $table->unsignedBigInteger('document_type_id')->nullable();
            $table->integer('template_id')->nullable();
            $table->integer('category_id')->nullable();
            $table->string('signature_request_document_id')->nullable()->comment('digisigner document id for other document like W9 and etc.');
            $table->integer('document_response_status')->comment('0 for no action')->default(0);
            $table->text('user_request_change_message')->nullable();
            $table->enum('document_uploaded_type', ['manual_doc', 'secui_doc_uploaded'])->default('manual_doc');
            $table->string('description')->nullable();
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
        Schema::dropIfExists('documents');
    }
};
