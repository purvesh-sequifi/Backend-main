<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterDefaultsOnLegacyApiDataNullTable extends Migration
{
    public function up()
    {
        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            $table->string('sales_alert')->nullable()->default(null)->change();
            $table->string('missingrep_alert')->nullable()->default(null)->change();
            $table->string('closedpayroll_alert')->nullable()->default(null)->change();
            $table->string('repredline_alert')->nullable()->default(null)->change();
            $table->string('locationredline_alert')->nullable()->default(null)->change();
            $table->string('people_alert')->nullable()->default(null)->change();

        });
    }

    public function down()
    {
        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            // Revert default to previous state — replace with actual previous defaults if any
            $table->string('sales_alert')->default('')->change();
            $table->string('missingrep_alert')->default('')->change();
            $table->string('closedpayroll_alert')->default('')->change();
            $table->string('repredline_alert')->default('')->change();
            $table->string('locationredline_alert')->default('')->change();
            $table->string('people_alert')->default('')->change();
        });
    }
}
