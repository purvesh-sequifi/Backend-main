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
        Schema::create('custom_field_history', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('payroll_id');
            $table->integer('column_id');
            $table->string('value');
            $table->text('comment')->nullable();
            $table->integer('approved_by')->integer(1);
            $table->tinyInteger('is_mark_paid')->default(0);
            $table->tinyInteger('is_next_payroll')->default(0);
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
        Schema::dropIfExists('custom_field_history');
    }
};
