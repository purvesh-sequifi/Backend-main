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
        Schema::create('sequi_docs_email_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('tempate_id')->nullable();
            $table->string('email_template_name')->nullable();
            $table->integer('unique_email_template_code')->nullable()->unique();
            $table->text('tmp_page_info')->nullable();
            $table->integer('category_id')->nullable();
            $table->string('email_subject')->nullable();
            $table->string('email_trigger')->nullable();
            $table->string('email_description')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->longText('email_content')->nullable();
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
        Schema::dropIfExists('sequi_docs_email_settings');
    }
};
