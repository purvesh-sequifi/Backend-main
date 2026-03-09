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
        Schema::table('onboarding_employees', function (Blueprint $table) {
            // Drop unique constraints first
            $table->dropUnique(['email']);
            $table->dropUnique(['mobile_no']);

            // Modify columns without unique constraint
            $table->string('email')->change();
            $table->string('mobile_no')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('onboarding_employees', function (Blueprint $table) {
            $table->string('email')->unique()->change();
            $table->string('mobile_no')->unique()->nullable()->change();
        });
    }
};
