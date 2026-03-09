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
        Schema::table('user_organization_history', function (Blueprint $table) {
            //
            $table->integer('existing_employee_new_manager_id')->nullable()->after('sub_position_id');
            $table->integer('is_manager')->nullable()->after('existing_employee_new_manager_id');
            $table->integer('old_is_manager')->nullable()->after('is_manager');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_organization_history', function (Blueprint $table) {
            //
            $table->dropColumn('existing_employee_new_manager_id');
            $table->dropColumn('is_manager');
            $table->dropColumn('old_is_manager');
        });
    }
};
