<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTaxHistory extends Model
{
    use HasFactory;

    protected $table = 'user_tax_history';

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
     * Get a specific field's old value (auto-decrypts if encrypted).
     */
    public function getOldValue(string $field)
    {
        $value = $this->old_values[$field] ?? null;

        if ($value && in_array($field, ['social_sequrity_no', 'business_ein']) && function_exists('dataDecrypt')) {
            return dataDecrypt($value);
        }

        return $value;
    }

    /**
     * Get a specific field's new value (auto-decrypts if encrypted).
     */
    public function getNewValue(string $field)
    {
        $value = $this->new_values[$field] ?? null;

        if ($value && in_array($field, ['social_sequrity_no', 'business_ein']) && function_exists('dataDecrypt')) {
            return dataDecrypt($value);
        }

        return $value;
    }

    /**
     * Check if a field was changed.
     */
    public function wasFieldChanged(string $field): bool
    {
        return in_array($field, $this->changed_fields ?? []);
    }
}


