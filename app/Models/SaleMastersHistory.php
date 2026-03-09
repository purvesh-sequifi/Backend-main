<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleMastersHistory extends Model
{
    use HasFactory;

    protected $table = 'sale_masters_history';

    protected $fillable = [
        'sale_master_id',
        'pid',
        'change_type',
        'changed_by',
        'data_source_type',
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
     * Get the sale that this history belongs to.
     */
    public function saleMaster(): BelongsTo
    {
        return $this->belongsTo(SalesMaster::class, 'sale_master_id');
    }

    /**
     * Get the user who made this change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope to get history for a specific sale.
     */
    public function scopeForSale($query, int $saleId)
    {
        return $query->where('sale_master_id', $saleId);
    }

    /**
     * Scope to get history by PID.
     */
    public function scopeForPid($query, string $pid)
    {
        return $query->where('pid', $pid);
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
     * Scope to get changes for specific field.
     */
    public function scopeFieldChanged($query, string $field)
    {
        return $query->whereJsonContains('changed_fields', $field);
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
