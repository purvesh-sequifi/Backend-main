<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalSaleWorker extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'external_sale_worker';

    protected $fillable = [
        'user_id',
        'pid',
        'type',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Get the user associated with this record
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the sale/product associated with this record
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(SalesMaster::class, 'pid', 'pid');
    }

    /**
     * Get the type as a readable string
     */
    public function getTypeNameAttribute(): string
    {
        $types = [
            1 => 'Self Gen',
            2 => 'Closer',
            3 => 'Setter',
        ];

        return $types[$this->type] ?? 'Unknown';
    }

    /**
     * Scope a query to only include self gen records
     */
    public function scopeSelfGen($query)
    {
        return $query->where('type', 1);
    }

    /**
     * Scope a query to only include closer records
     */
    public function scopeCloser($query)
    {
        return $query->where('type', 2);
    }

    /**
     * Scope a query to only include setter records
     */
    public function scopeSetter($query)
    {
        return $query->where('type', 3);
    }
}
