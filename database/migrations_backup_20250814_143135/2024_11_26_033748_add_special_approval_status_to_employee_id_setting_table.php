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
        Schema::table('employee_id_setting', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_id_setting', 'special_approval_status')) {
                $table->tinyInteger('special_approval_status')->default(0)->after('require_approval_status');
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
        Schema::table('employee_id_setting', function (Blueprint $table) {
            //
        });
    }
};
