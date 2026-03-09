<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAdditionalOfficeOverrideHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_additional_office_override_histories';

    protected $fillable = [
        'user_id',
        'updater_id',
        'override_effective_date',
        'effective_end_date',
        'state_id',
        'product_id',
        'office_id',
        'office_overrides_amount',
        'office_overrides_type',
        'old_office_overrides_amount',
        'old_office_overrides_type',
        'tiers_id',
        'old_tiers_id',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function state(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'state_id');
    }

    public function office(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'office_id');
    }

    public function scopeNearestToDate($query, $user_id, $currentDate, $office_ids)
    {
        return $query
            ->select('user_additional_office_override_histories.*')
            ->join(
                \DB::raw('(SELECT MAX(`override_effective_date`) AS max_override_date FROM `user_additional_office_override_histories` WHERE `user_id` = '.$user_id.' AND `override_effective_date` <= '.$currentDate.' AND `office_id` IN ('.$office_ids.')) subquery'),
                function ($join) {
                    $join->on('user_additional_office_override_histories.override_effective_date', '=', 'subquery.max_override_date');
                }
            )
            ->where('user_additional_office_override_histories.user_id', $user_id)
            ->whereIn('user_additional_office_override_histories.office_id', $office_ids);
    }

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function tearsRange(): HasMany
    {
        return $this->hasMany(UserAdditionalOfficeOverrideHistoryTiersRange::class, 'user_add_office_override_history_id', 'id');
    }

    public function product(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }

    /**
     * Get the custom sales field for this history record
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
