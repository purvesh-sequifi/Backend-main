<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop unique constraints first
            $table->dropUnique(['email']);
            $table->dropUnique(['mobile_no']);

            // Modify columns without unique constraint
            $table->string('email')->change();
            $table->string('mobile_no')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Reapply unique constraints on rollback
            $table->string('email')->unique()->change();
            $table->string('mobile_no')->unique()->nullable()->change();
        });
    }
};
