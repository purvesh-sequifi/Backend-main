<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserManagerHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_manager_histories';

    protected $fillable = [
        'user_id',
        'updater_id',
        'effective_date',
        'effective_end_date',
        'manager_id',
        'old_manager_id',
        'team_id',
        'old_team_id',
        'position_id',
        'old_position_id',
        'sub_position_id',
        'old_sub_position_id',
        'action_item_status',
        'system_generated',
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function manager(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'manager_id')->select('id', 'first_name', 'last_name');
    }

    public function oldManager(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'old_manager_id')->select('id', 'first_name', 'last_name');
    }

    public function team(): HasOne
    {
        return $this->hasOne(ManagementTeam::class, 'id', 'team_id')->select('id', 'team_name');
    }

    public function oldTeam(): HasOne
    {
        return $this->hasOne(ManagementTeam::class, 'id', 'old_team_id')->select('id', 'team_name');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'manager_id')->select('id', 'first_name', 'last_name');
    }

    public function managerInfo(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'manager_id');
    }
}
