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
            $table->string('hiring_signature')->nullable()->after('hired_by_uid');
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
            $table->dropColumn('hiring_signature');
        });
    }
};
