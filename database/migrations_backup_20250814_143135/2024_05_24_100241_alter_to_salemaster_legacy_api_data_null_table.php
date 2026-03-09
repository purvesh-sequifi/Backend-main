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
        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            $table->integer('panel_id')->after('prospect_id');
            $table->string('panel_type', 20)->nullable()->after('prospect_id');

        });

        Schema::table('sale_masters', function (Blueprint $table) {
            $table->integer('panel_id')->after('prospect_id');
            $table->string('panel_type', 20)->nullable()->after('prospect_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            //
            $table->dropColumn('panel_id');
            $table->dropColumn('panel_type');
        });
        Schema::table('sale_masters', function (Blueprint $table) {
            //
            $table->dropColumn('panel_id');
            $table->dropColumn('panel_type');
        });
    }
};
