<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projection_user_overrides', function (Blueprint $table) {
            $table->string('calculated_redline')->nullable()->after('total_override');
            $table->string('calculated_redline_type')->nullable()->after('calculated_redline');
        });
    }

    public function down(): void
    {
        Schema::table('projection_user_overrides', function (Blueprint $table) {
            $table->dropColumn(['calculated_redline', 'calculated_redline_type']);
        });
    }
};
