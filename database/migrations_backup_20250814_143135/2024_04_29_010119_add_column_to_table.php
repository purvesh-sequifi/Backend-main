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
            $table->date('pay_period_from')->nullable(); // Example: Adding a nullable string column
            $table->date('pay_period_to')->nullable(); // Example: Adding a nullable string column

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
