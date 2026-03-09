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

        Schema::table('positions', function (Blueprint $table) {
            $table->enum('can_act_as_both_setter_and_closer', ['0', '1'])->default('1');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn('can_act_as_both_setter_and_closer');
        });
    }
};
