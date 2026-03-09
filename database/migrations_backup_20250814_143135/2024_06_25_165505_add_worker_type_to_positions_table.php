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
        if (Schema::hasColumn('positions', 'worker_type')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->dropColumn('worker_type');
            });
        }

        Schema::table('positions', function (Blueprint $table) {
            $table->string('worker_type', 50)->default('1099')->nullable()->comment('W9, 1099')->after('position_name');
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
            //
        });
    }
};
