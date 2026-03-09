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
        Schema::create('new_sequi_docs_documents', function (Blueprint $table) {
            // user and template data
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('user_id_from', ['users', 'onboarding_employees'])->default('users');
            $table->integer('is_external_recipient')->comment('0 for no, 1 for Yes')->default(0);
            $table->integer('external_user_name')->nullable();
            $table->integer('external_user_email')->nullable();

            $table->integer('template_id')->nullable();
            $table->integer('category_id')->nullable();
            $table->string('description')->nullable();
            $table->integer('is_active')->nullable()->comment('0 not active , 1 for active doc')->default(1);
            $table->dateTime('document_inactive_date')->nullable();
            $table->integer('send_by')->nullable()->comment('Document send by user id');
            $table->tinyInteger('is_document_resend')->comment('0 for send , 1 for resend')->default(0)->nullable();
            $table->integer('upload_document_type_id')->comment('id from new_sequi_docs_upload_document_types')->nullable();

            // Document and its response status
            $table->text('un_signed_document')->nullable()->comment('doc before sign');
            $table->dateTime('document_send_date')->nullable();
            $table->integer('document_response_status')->comment('0 for no action')->default(0);
            $table->dateTime('document_response_date')->nullable();
            $table->text('user_request_change_message')->nullable();
            $table->enum('document_uploaded_type', ['manual_doc', 'secui_doc_uploaded'])->default('manual_doc');

            // signature request document id its response status for sin
            $table->integer('envelope_id')->nullable();
            $table->string('envelope_password')->nullable();
            $table->string('signature_request_id')->nullable();
            $table->string('signature_request_document_id')->nullable()->comment('signature requested document id');
            $table->tinyInteger('signed_status')->comment('0=not signed,1=signed')->default(0);
            $table->dateTime('signed_date')->nullable();
            $table->text('signed_document')->nullable()->comment('doc after sign');

            $table->tinyInteger('send_reminder')->nullable()->default(0)->comment('0 for no , 1 for yes');
            $table->tinyInteger('reminder_in_days')->nullable()->default(0);
            $table->tinyInteger('max_reminder_times')->nullable()->default(0);
            $table->tinyInteger('reminder_done_times')->nullable()->default(0);
            $table->tinyInteger('is_post_hiring_document')->nullable()->default(0)->comment('0 for no , 1 for yes');
            $table->tinyInteger('is_sign_required_for_hire')->nullable()->default(0)->comment('0 for no , 1 for yes');

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
        Schema::dropIfExists('new_sequi_docs_documents');
    }
};
