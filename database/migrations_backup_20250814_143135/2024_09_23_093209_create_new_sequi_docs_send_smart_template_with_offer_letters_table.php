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
        Schema::create('new_sequi_docs_send_smart_template_with_offer_letters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->longText('template_content');
            $table->smallInteger('template_id');
            $table->smallInteger('to_send_template_id')->comment('template id for send with offer letter');
            $table->smallInteger('category_id')->nullable()->comment('to send template category id');
            $table->smallInteger('is_sign_required_for_hire')->default(0)->comment('0 for optional , 1 for Mandatory');
            $table->smallInteger('is_post_hiring_document')->default(0)->comment('1 for post Hiring , 0 for Onboarding Document');
            $table->tinyInteger('is_document_for_upload')->nullable()->default(0)->comment('0 for Signature , 1 for upload file');
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
        Schema::dropIfExists('new_sequi_docs_send_smart_template_with_offer_letters');
    }
};
