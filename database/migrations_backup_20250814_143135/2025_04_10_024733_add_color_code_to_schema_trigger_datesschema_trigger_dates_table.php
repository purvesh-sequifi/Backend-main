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
        if (! Schema::hasColumn('schema_trigger_dates', 'color_code')) {
            Schema::table('schema_trigger_dates', function (Blueprint $table) {
                $table->string('color_code')->nullable()->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('schema_trigger_dates', function (Blueprint $table) {
            //
        });
    }
};
