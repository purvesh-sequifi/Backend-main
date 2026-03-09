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
        Schema::table('hiring_status', function (Blueprint $table) {
            // Check if the column exists
            if (Schema::hasColumn('hiring_status', 'show_on_card')) {
                // Drop the existing column and add the new one
                $table->dropColumn('show_on_card');
                // $table->string('show_on_card')->default(1)->after('colour_code')->comment('1=>show, 0=>hide, set for Pipeline Card');
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
        Schema::table('hiring_status', function (Blueprint $table) {
            //
        });
    }
};
