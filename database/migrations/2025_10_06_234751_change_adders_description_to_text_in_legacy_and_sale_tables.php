<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change adders_description from varchar(255) to text in legacy_api_raw_data_histories table
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->text('adders_description')->change();
        });

        // Change adders_description from varchar(255) to text in sale_masters table
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->text('adders_description')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert adders_description from text back to varchar(255) in legacy_api_raw_data_histories table
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->string('adders_description', 255)->change();
        });

        // Revert adders_description from text back to varchar(255) in sale_masters table
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->string('adders_description', 255)->change();
        });
    }
};
