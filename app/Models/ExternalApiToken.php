<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Carbon\Carbon;

class ExternalApiToken extends Model
{
    use HasFactory;

    protected $table = 'external_api_tokens';

    protected $fillable = [
        'name',
        'token',
        'scopes',
        'expires_at',
        'last_used_at',
        'created_by_ip',
        'last_used_ip',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    // Available API scopes with improved naming
    public const AVAILABLE_SCOPES = [
        'payroll:read' => 'Read payroll reports and data',
        'payroll:export' => 'Export payroll data to external formats',
        'users:read' => 'Read users list and employee data',
        'users:export' => 'Export users data to external formats',
        'reports:read' => 'Read all system reports',
        'reports:export' => 'Export report data',
        'admin:tokens' => 'Manage API tokens (admin only)',
        'admin:full_access' => 'Full administrative access to all endpoints',
    ];

    // Token expiration constants
    public const DEFAULT_EXPIRATION_DAYS = 90;

    public const MAXIMUM_EXPIRATION_DAYS = 365;

    public const ROTATION_WARNING_DAYS = 30;

    /**
     * Check if token has a specific scope
     */
    public function hasRequiredScope(string $requiredScope): bool
    {
        if (empty($this->scopes)) {
            return false;
        }

        return in_array($requiredScope, $this->scopes) || $this->hasFullAdminAccess();
    }

    /**
     * Check if token has any of the provided scopes
     */
    public function hasAnyRequiredScope(array $requiredScopes): bool
    {
        if (empty($this->scopes)) {
            return false;
        }

        // Admin has all permissions
        if ($this->hasFullAdminAccess()) {
            return true;
        }

        return ! empty(array_intersect($requiredScopes, $this->scopes));
    }

    /**
     * Check if token has full admin access
     */
    public function hasFullAdminAccess(): bool
    {
        return in_array('admin:full_access', $this->scopes ?? []);
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if token is active (not expired)
     */
    public function isActive(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Check if token needs rotation soon
     */
    public function needsRotationSoon(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->diffInDays(now()) <= self::ROTATION_WARNING_DAYS;
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }

        return max(0, $this->expires_at->diffInDays(now()));
    }

    /**
     * Update token usage tracking
     */
    public function recordUsage(?string $ipAddress = null): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ipAddress ?? request()->ip(),
        ]);
    }

    /**
     * Revoke token by setting expiration to now
     */
    public function revokeToken(): bool
    {
        return $this->update(['expires_at' => now()]);
    }

    /**
     * Scope for active (non-expired) tokens
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope for expired tokens
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope for tokens expiring soon
     */
    public function scopeExpiringSoon($query, ?int $days = null)
    {
        $thresholdDays = $days ?? self::ROTATION_WARNING_DAYS;

        return $query->active()
            ->where('expires_at', '<=', now()->addDays($thresholdDays));
    }

    /**
     * Scope for tokens used recently
     */
    public function scopeUsedRecently($query, int $hours = 24)
    {
        return $query->where('last_used_at', '>=', now()->subHours($hours));
    }
}
