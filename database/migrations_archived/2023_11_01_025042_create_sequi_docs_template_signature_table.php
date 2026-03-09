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
        Schema::create('sequi_docs_template_signature', function (Blueprint $table) {
            $table->id();
            $table->integer('template_id');
            $table->integer('category_id');
            $table->integer('additional_signature');
            $table->integer('required_check');
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
        Schema::dropIfExists('sequi_docs_template_signature');
    }
};
