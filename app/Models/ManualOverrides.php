<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManualOverrides extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'manual_overrides';

    protected $fillable = [
        'manual_user_id',
        'user_id',
        'overrides_amount',
        'overrides_type',
        'effective_date',
        'product_id',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function ManualOverridesHistory(): HasMany
    {
        return $this->hasMany(\App\Models\ManualOverridesHistory::class, 'manual_overrides_id', 'id')->with('user', 'manualUser', 'updatedByUser')->orderBy('effective_date', 'asc');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name');
    }

    public function manualUser(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'manual_user_id')->select('id', 'first_name', 'last_name');
    }

    /**
     * Get the custom sales field for this manual override
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
