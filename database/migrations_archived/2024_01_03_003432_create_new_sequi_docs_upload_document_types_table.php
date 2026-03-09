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
        Schema::create('new_sequi_docs_upload_document_types', function (Blueprint $table) {
            $table->bigIncrements('id')->autoIncrement();
            $table->string('document_name')->nullable();
            $table->tinyInteger('is_deleted')->nullable()->default(0)->comment('1 for deleted, 0 for not deleted');
            $table->dateTime('delete_date')->nullable();
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
        Schema::dropIfExists('new_sequi_docs_upload_document_types');
    }
};
