<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCompRatePrecisionInUserCommissionTable extends Migration
{
    public function up()
    {
        Schema::table('user_commission', function (Blueprint $table) {
            $table->decimal('comp_rate', 8, 4)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('user_commission', function (Blueprint $table) {
            $table->decimal('comp_rate', 8, 2)->nullable()->change(); // revert to original
        });
    }
}
