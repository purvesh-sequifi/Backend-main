<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdditionalLocations extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'additional_locations';

    protected $fillable = [
        'user_id',
        'state_id',
        'city_id',
        'overrides_amount',
        'overrides_type',
        'office_id',
        'effective_date',
        'effective_end_date',
        'updater_id',
        'archived_at',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
    ];

    public function state(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'state_id');
    }

    public function city(): HasOne
    {
        return $this->hasOne(\App\Models\Cities::class, 'id', 'city_id');
    }

    public function office(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'office_id');
    }

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }

    /**
     * Get the custom sales field for this additional location
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
