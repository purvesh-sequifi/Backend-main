<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserIsManagerHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_is_manager_histories';

    protected $fillable = [
        'user_id',
        'updater_id',
        'effective_date',
        'effective_end_date',
        'is_manager',
        'old_is_manager',
        'position_id',
        'old_position_id',
        'sub_position_id',
        'old_sub_position_id',
        'action_item_status',
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }
}
