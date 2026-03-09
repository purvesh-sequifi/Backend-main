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
        Schema::create('new_sequi_docs_templates', function (Blueprint $table) {
            // template data
            $table->bigIncrements('id')->autoIncrement();
            $table->integer('category_id')->nullable();
            $table->string('template_name')->nullable();
            $table->string('template_description')->nullable();
            $table->longText('template_content')->nullable();
            $table->tinyInteger('completed_step')->nullable()->default(0);
            $table->tinyInteger('is_template_ready')->nullable()->default(0);
            $table->tinyInteger('recipient_sign_req')->nullable()->default(1);
            $table->integer('created_by')->nullable();

            // pdf data
            $table->integer('is_pdf')->nullable()->default(0)->comment('0 for no is blank template , 1 for template is pdf');
            $table->string('pdf_file_path')->nullable();
            $table->json('pdf_file_other_parameter')->nullable();

            // Template email Data.
            $table->string('email_subject')->nullable();
            $table->longText('email_content')->nullable();
            $table->tinyInteger('send_reminder')->nullable()->default(0)->comment('0 for no , 1 for yes');
            $table->tinyInteger('reminder_in_days')->nullable()->default(0);
            $table->tinyInteger('max_reminder_times')->nullable()->default(0);
            $table->tinyInteger('is_deleted')->nullable()->default(0);
            $table->dateTime('template_delete_date')->nullable();

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
        Schema::dropIfExists('new_sequi_docs_templates');
    }
};
