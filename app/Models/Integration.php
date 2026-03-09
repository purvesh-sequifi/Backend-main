<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    use HasFactory;

    protected $table = 'integrations';

    protected $fillable = [
        'name',
        'description',
        'value',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to get only active integrations
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to get FieldRoutes integrations
     */
    public function scopeFieldRoutes($query)
    {
        return $query->where('name', 'FieldRoutes');
    }

    /**
     * Get employees for this integration/office
     */
    public function employees(): HasMany
    {
        return $this->hasMany(FrEmployeeData::class, 'office_name', 'description');
    }

    /**
     * Check if integration is active
     */
    public function isActive()
    {
        return $this->status == 1;
    }

    /**
     * Get decrypted API credentials
     * Note: You'll need to implement proper decryption based on your encryption method
     */
    public function getDecryptedValue()
    {
        // Placeholder - implement proper decryption
        return $this->value;
    }
}
