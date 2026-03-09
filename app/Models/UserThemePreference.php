<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserThemePreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'theme_name',
        'theme_config',
        'is_active',
    ];

    protected $casts = [
        'theme_config' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the theme preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get active theme preferences.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the active theme for a specific user.
     */
    public static function getActiveThemeForUser(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Set a theme as active for a user (deactivates other themes).
     * Uses database transaction and proper locking to prevent race conditions.
     */
    public static function setActiveThemeForUser(int $userId, string $themeName, ?array $themeConfig = null): self
    {
        return DB::transaction(function () use ($userId, $themeName, $themeConfig) {
            // Lock the user's theme preferences to prevent concurrent modifications
            static::where('user_id', $userId)->lockForUpdate()->get();

            // Deactivate all existing themes for the user
            static::where('user_id', $userId)->update(['is_active' => false]);

            // Create or update the active theme
            return static::updateOrCreate(
                [
                    'user_id' => $userId,
                    'theme_name' => $themeName,
                ],
                [
                    'theme_config' => $themeConfig ?? [],
                    'is_active' => true,
                ]
            );
        });
    }
}
