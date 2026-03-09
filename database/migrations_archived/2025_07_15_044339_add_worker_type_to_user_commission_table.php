<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkerTypeToUserCommissionTable extends Migration
{
    public function up()
    {
        Schema::table('user_commission', function (Blueprint $table) {
            $table->string('worker_type')->default('internal')->after('user_id')->comment('internal or external');
        });
    }

    public function down()
    {
        Schema::table('user_commission', function (Blueprint $table) {
            $table->dropColumn('worker_type');
        });
    }
}
