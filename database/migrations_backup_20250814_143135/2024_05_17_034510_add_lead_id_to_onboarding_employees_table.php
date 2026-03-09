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
            if (! Schema::hasColumn('onboarding_employees', 'lead_id')) {
                $table->bigInteger('lead_id')->nullable()->after('user_id');
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
        Schema::table('onboarding_employees', function (Blueprint $table) {
            //
        });
    }
};
