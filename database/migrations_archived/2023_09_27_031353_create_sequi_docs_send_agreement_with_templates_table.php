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
        Schema::create('sequi_docs_send_agreement_with_templates', function (Blueprint $table) {
            $table->id();
            $table->integer('template_id');
            $table->integer('category_id')->nullable();
            $table->integer('position_id')->comment('position_id for send aggrement template to person');
            $table->integer('aggrement_template_id')->comment('agreement template id for send');
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
        Schema::dropIfExists('sequi_docs_send_agreement_with_templates');
    }
};
