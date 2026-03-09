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
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->after('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->dropColumn('template_id');
        });
    }
};
