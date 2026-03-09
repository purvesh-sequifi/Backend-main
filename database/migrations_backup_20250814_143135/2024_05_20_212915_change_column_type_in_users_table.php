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
        Schema::table('users', function (Blueprint $table) {
            // Change column type from VARCHAR to TEXT
            $table->text('business_ein')->change();
            $table->text('social_sequrity_no')->change();
            $table->text('account_no')->change();
            $table->text('routing_no')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('business_ein', 255)->change();
            $table->string('social_sequrity_no', 255)->change();
            $table->string('account_no', 255)->change();
            $table->string('routing_no', 255)->change();
            //
        });
    }
};
