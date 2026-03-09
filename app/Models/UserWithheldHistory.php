<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserWithheldHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_withheld_history';

    protected $fillable = [
        'user_id',
        'updater_id',
        'product_id',
        'old_product_id',
        'self_gen_user',
        'old_self_gen_user',
        'withheld_amount',
        'old_withheld_amount',
        'withheld_type',
        'old_withheld_type',
        'withheld_effective_date',
        'effective_end_date',
        'position_id',
        'core_position_id',
        'sub_position_id',
        'action_item_status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function subposition(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'sub_position_id')->select('id', 'position_name');
    }

    public function position(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id')->select('id', 'position_name');
    }

    public function product(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }
}
