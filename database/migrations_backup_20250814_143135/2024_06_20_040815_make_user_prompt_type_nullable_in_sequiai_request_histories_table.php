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
        Schema::table('sequiai_request_histories', function (Blueprint $table) {
            $table->text('user_prompt_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sequiai_request_histories', function (Blueprint $table) {
            $table->text('user_prompt_type')->nullable(false)->change();
        });
    }
};
