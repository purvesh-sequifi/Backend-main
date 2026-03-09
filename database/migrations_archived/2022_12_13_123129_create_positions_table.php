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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('position_name')->nullable();
            $table->unsignedBigInteger('department_id');
            $table->integer('parent_id')->nullable();
            $table->integer('org_parent_id')->nullable();
            $table->integer('group_id')->nullable();
            $table->integer('is_manager')->nullable();
            $table->integer('is_stack')->nullable();
            $table->integer('order_by')->nullable();
            $table->tinyInteger('setup_status');
            $table->timestamps();
            $table->foreign('department_id')->references('id')
                ->on('departments')->onDelete('cascade');

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('positions');
    }
};
