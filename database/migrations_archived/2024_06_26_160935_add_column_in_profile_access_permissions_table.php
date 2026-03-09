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
        Schema::table('profile_access_permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('profile_access_permissions', 'profile_access_for')) {
                $table->string('profile_access_for')->nullable()->after('position_id');
            }
            if (! Schema::hasColumn('profile_access_permissions', 'payroll_history')) {
                $table->integer('payroll_history')->nullable()->after('profile_access_for');
            }
            if (! Schema::hasColumn('profile_access_permissions', 'reset_password')) {
                $table->integer('reset_password')->nullable()->after('payroll_history');
            }
            if (! Schema::hasColumn('profile_access_permissions', 'type')) {
                $table->string('type')->nullable()->after('reset_password');
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
        Schema::table('profile_access_permissions', function (Blueprint $table) {
            //
        });
    }
};
