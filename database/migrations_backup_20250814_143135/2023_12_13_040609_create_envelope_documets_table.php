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
        Schema::create('envelope_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('envelope_id');

            // 0: no
            // 1: yes
            $table->tinyInteger('is_mandatory')->default(1);

            // 0: no
            // 1: yes
            $table->tinyInteger('upload_by_user')->default(0);

            // 0: incompleted
            // 1: completed
            // Can add more status here like reject etc
            $table->tinyInteger('status')->default(0);

            // 0: public cloud
            // 1: local public storage
            // 2: s3 public
            // 3: s3 private
            $table->tinyInteger('pdf_storage_type')->default(0);
            // pdf before esign digi sign and form filling
            $table->string('initial_pdf_path')->nullable();

            // pdf after esign digi sign and form filling
            $table->string('processed_pdf_path')->nullable();

            $table->tinyInteger('is_pdf')->default(0);
            $table->json('pdf_file_other_parameter')->nullable();
            $table->tinyInteger('is_sign_required_for_hire')->default(0);
            $table->string('template_name')->nullable();
            $table->tinyInteger('is_post_hiring_document')->default(0);
            $table->json('pdf_pages_as_image')->nullable();
            $table->integer('template_category_id')->nullable();
            $table->string('template_category_name')->nullable();

            $table->foreign('envelope_id')
                ->references('id')
                ->on('envelopes')
                ->onDelete('cascade');
            $table->softDeletes($column = 'deleted_at', $precision = 0);
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
        Schema::dropIfExists('envelope_documents');
    }
};
