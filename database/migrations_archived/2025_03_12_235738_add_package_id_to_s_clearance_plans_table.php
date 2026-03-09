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
        Schema::table('s_clearance_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('s_clearance_plans', 'package_id')) {
                $table->string('package_id')->nullable()->after('plan_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('s_clearance_plans', function (Blueprint $table) {
            //
        });
    }
};
