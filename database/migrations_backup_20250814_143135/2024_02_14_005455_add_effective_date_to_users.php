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
        Schema::table('users', function (Blueprint $table) {
            //
            $table->date('manager_id_effective_date')->nullable()->after('manager_id');
            $table->date('team_id_effective_date')->nullable()->after('team_id');
            $table->date('is_manager_effective_date')->nullable()->after('is_manager');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->dropColumn('manager_id_effective_date');
            $table->dropColumn('team_id_effective_date');
            $table->dropColumn('is_manager_effective_date');
        });
    }
};
