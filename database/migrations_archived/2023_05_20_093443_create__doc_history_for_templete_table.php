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
        Schema::create('doc_history_for_templete', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('employee_position')->nullable();
            $table->string('Company_Name')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('currentdate')->nullable();
            $table->string('building_no')->nullable();
            $table->string('type')->nullable();
            $table->string('pdf')->nullable();
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
        Schema::dropIfExists('doc_history_for_templete');
    }
};
