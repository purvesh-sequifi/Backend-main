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
            $table->string('experience_level')->nullable(); // Add the nullable VARCHAR column
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
            $table->dropColumn('experience_level'); // Drop the column if rolled back
        });
    }
};
