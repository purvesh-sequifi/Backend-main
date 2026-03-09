<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserSelfGenCommmissionHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_self_gen_commmission_histories';

    protected $fillable = [
        'user_id',
        'updater_id',
        'commission',
        'commission_type',
        'commission_effective_date',
        'old_commission',
        'old_commission_type',
        'old_commission_effective_date',
        'position_id',
        'sub_position_id',
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
}
