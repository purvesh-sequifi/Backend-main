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
        Schema::table('automation_action_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('automation_action_logs', 'onboarding_id')) {
                $table->unsignedBigInteger('onboarding_id')->nullable()->after('lead_id');
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
        Schema::table('automation_action_logs', function (Blueprint $table) {
            //
        });
    }
};
