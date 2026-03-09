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
        Schema::table('leads', function (Blueprint $table) {
            // Check if the column exists
            if (! Schema::hasColumn('leads', 'pipeline_status_id')) {
                // Add the new column
                $table->string('pipeline_status_id')->default(1)->after('type');
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
