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
        Schema::create('new_sequi_docs_template_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('template_id')->nullable();
            $table->integer('category_id')->nullable();
            $table->integer('position_id')->nullable();
            $table->enum('position_type', ['permission', 'receipient'])->default('permission');
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
        Schema::dropIfExists('new_sequi_docs_template_permissions');
    }
};
