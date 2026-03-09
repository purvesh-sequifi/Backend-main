<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeductionAuditHistory extends Model
{
    use HasFactory;

    protected $table = 'user_deduction_audit_history';

    protected $fillable = [
        'source_id',
        'product_id',
        'position_name',
        'effective_date',
        'user_id',
        'change_type',
        'changed_by',
        'change_source',
        'old_values',
        'new_values',
        'changed_fields',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the source record this audit belongs to.
     */
    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(UserDeductionHistory::class, 'source_id');
    }

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
     * Scope to filter by change source.
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('change_source', $source);
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

