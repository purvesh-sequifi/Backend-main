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
        Schema::create('modules_with_permission', function (Blueprint $table) {
            $table->id();
            $table->integer('module_id');
            $table->integer('module_tab_id');
            $table->string('submodule');
            $table->string('action')->nullable();
            $table->tinyInteger('status')->default('1');
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
        // Schema::dropIfExists('permission_submodules');
        Schema::dropIfExists('modules_with_permission');
    }
};
