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
        Schema::table('user_transfer_history', function (Blueprint $table) {
            $table->string('redline', 25)->nullable()->change();
            $table->string('old_redline', 25)->nullable()->change();
            $table->string('self_gen_redline', 25)->nullable()->change();
            $table->string('old_self_gen_redline', 25)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_transfer_history', function (Blueprint $table) {
            //
        });
    }
};
