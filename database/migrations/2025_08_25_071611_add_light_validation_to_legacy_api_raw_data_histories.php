<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->boolean('light_validation')
                ->default(false)
                ->after('data_source_type');
        });
    }

    public function down(): void
    {
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->dropColumn('light_validation');
        });
    }
};
