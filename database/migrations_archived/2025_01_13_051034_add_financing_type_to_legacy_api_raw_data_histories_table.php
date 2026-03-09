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
        DB::statement("ALTER TABLE `legacy_api_raw_data_histories` CHANGE `closer1_m1` `closer1_m1` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0';");
        DB::statement("ALTER TABLE `legacy_api_raw_data_histories` CHANGE `closer2_m1` `closer2_m1` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0';");
        DB::statement("ALTER TABLE `legacy_api_raw_data_histories` CHANGE `setter1_m1` `setter1_m1` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0';");
        DB::statement("ALTER TABLE `legacy_api_raw_data_histories` CHANGE `setter2_m1` `setter2_m1` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0';");
        DB::statement("ALTER TABLE `legacy_api_raw_data_histories` CHANGE `closer1_m2` `closer1_m2` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0';");
        DB::statement("ALTER TABLE `legacy_api_raw_data_histories` CHANGE `closer2_m2` `closer2_m2` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0';");
        DB::statement("ALTER TABLE `legacy_api_raw_data_histories` CHANGE `setter1_m2` `setter1_m2` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0';");
        DB::statement("ALTER TABLE `legacy_api_raw_data_histories` CHANGE `setter2_m2` `setter2_m2` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0';");
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->string('financing_type')->nullable()->after('financing_term');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            //
        });
    }
};
