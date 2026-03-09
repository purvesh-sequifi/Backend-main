<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create user_theme_preferences pivot table instead of adding arena_theme directly to users table.
 * This approach keeps the users table lightweight and provides more flexibility for theme management.
 *
 * Usage Examples:
 * - $user->setActiveTheme('arena-2.0', ['color' => 'blue', 'layout' => 'compact']);
 * - $currentTheme = $user->getCurrentTheme();
 * - $themeConfig = $user->activeThemePreference->theme_config;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_theme_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('theme_name')->default('default');
            $table->json('theme_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index('user_id');
            $table->index(['user_id', 'is_active']);

            // Ensure one active theme per user
            $table->unique(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_theme_preferences');
    }
};
