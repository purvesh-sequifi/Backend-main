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
        Schema::table('custom_field', function (Blueprint $table) {
            $table->integer('user_id')->nullable()->change();
            $table->integer('payroll_id')->nullable()->change();
            $table->integer('column_id')->nullable()->change();
            $table->string('value')->nullable()->change();
            $table->text('comment')->nullable()->change();
            $table->integer('approved_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('custom_field', function (Blueprint $table) {
            //
        });
    }
};
