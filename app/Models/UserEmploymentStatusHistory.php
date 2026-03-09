<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmploymentStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'user_employment_status_history';

    protected $fillable = [
        'user_id',
        'change_type',
        'changed_by',
        'old_values',
        'new_values',
        'changed_fields',
        'reason',
        'ip_address',
        'user_agent',
        'effective_date',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'effective_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that this history belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who made this change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get the status relationship.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(UserStatus::class, 'status_id');
    }

    /**
     * Get the old status relationship.
     */
    public function oldStatus(): BelongsTo
    {
        return $this->belongsTo(UserStatus::class, 'old_status_id');
    }

    /**
     * Scope to get history for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recent history.
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope to filter by change type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('change_type', $type);
    }

    /**
     * Scope to get terminated users history.
     */
    public function scopeTerminated($query)
    {
        return $query->whereJsonContains('changed_fields', 'terminate')
            ->where('new_values->terminate', 1);
    }

    /**
     * Scope to get contract ended users history.
     */
    public function scopeContractEnded($query)
    {
        return $query->whereJsonContains('changed_fields', 'contract_ended')
            ->where('new_values->contract_ended', 1);
    }

    /**
     * Get a specific field's old value.
     */
    public function getOldValue(string $field)
    {
        return $this->old_values[$field] ?? null;
    }

    /**
     * Get a specific field's new value.
     */
    public function getNewValue(string $field)
    {
        return $this->new_values[$field] ?? null;
    }

    /**
     * Check if a field was changed.
     */
    public function wasFieldChanged(string $field): bool
    {
        return in_array($field, $this->changed_fields ?? []);
    }
}


