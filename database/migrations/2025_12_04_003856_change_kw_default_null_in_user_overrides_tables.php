<?php

declare(strict_types=1);

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
        Schema::table('user_overrides', function (Blueprint $table) {
            $table->string('kw')->nullable()->change();
        });

        Schema::table('user_overrides_lock', function (Blueprint $table) {
            $table->string('kw')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_overrides', function (Blueprint $table) {
            $table->string('kw')->nullable(false)->change();
        });

        Schema::table('user_overrides_lock', function (Blueprint $table) {
            $table->string('kw')->nullable(false)->change();
        });
    }
};
