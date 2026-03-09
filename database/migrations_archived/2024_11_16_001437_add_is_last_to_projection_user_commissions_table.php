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
        Schema::table('projection_user_commissions', function (Blueprint $table) {
            $table->tinyInteger('is_last')->default(0)->comment('Default 0, 1 = When last date hits')->after('type');
            $table->string('value_type')->default('m2')->comment('m2, reconciliation')->nullable(0)->after('is_last');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('projection_user_commissions', function (Blueprint $table) {
            //
        });
    }
};
