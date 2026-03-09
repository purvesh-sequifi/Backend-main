<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManualOverridesHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'manual_overrides_history';

    protected $fillable = [
        'manual_user_id',
        'manual_overrides_id',
        'user_id',
        'updated_by',
        'overrides_amount',
        'old_overrides_amount',
        'old_overrides_type',
        'overrides_type',
        'effective_date',
        'product_id',
        'updated_at',
        'custom_sales_field_id',     // Custom Sales Field feature
        'old_custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',

    ];

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name');
    }

    public function manualUser(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'manual_user_id')->select('id', 'first_name', 'last_name');
    }

    public function updatedByUser(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updated_by')->select('id', 'first_name', 'last_name', 'image');
    }

    /**
     * Get the custom sales field for this history record
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }

    /**
     * Get the old custom sales field for this history record
     */
    public function oldCustomSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'old_custom_sales_field_id');
    }
}
