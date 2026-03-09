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
        Schema::dropIfExists('custom_field');
        Schema::create('custom_field', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('payroll_id');
            $table->integer('column_id');
            $table->string('value');
            $table->text('comment');
            $table->integer('approved_by')->nullable();
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
        //
    }
};
