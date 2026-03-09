<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('tier_metrics', function (Blueprint $table) {
            DB::table('tier_metrics')
                ->where('id', 17) // Add your condition here
                ->update(['value' => 'Workers Recruited']); // Update the value as needed

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tier_metrics', function (Blueprint $table) {
            //
        });
    }
};
