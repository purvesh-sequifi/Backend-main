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
        Schema::table('product_milestone_histories', function (Blueprint $table) {
            $table->string('product_redline')->nullable()->after('clawback_exempt_on_ms_trigger_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_milestone_histories', function (Blueprint $table) {
            //
        });
    }
};
