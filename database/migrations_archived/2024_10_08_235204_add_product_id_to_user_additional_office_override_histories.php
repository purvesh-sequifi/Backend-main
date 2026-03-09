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
        Schema::table('user_additional_office_override_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('state_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_additional_office_override_histories', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
    }
};
