<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserOrganizationHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_organization_history';

    protected $fillable = [
        'user_id',
        'updater_id',
        'old_manager_id',
        'manager_id',
        'old_team_id',
        'team_id',
        'product_id',
        'effective_date',
        'effective_end_date',
        'position_id',
        'old_position_id',
        'sub_position_id',
        'old_sub_position_id',
        'existing_employee_new_manager_id',
        'is_manager',
        'old_is_manager',
        'self_gen_accounts',
        'old_self_gen_accounts',
    ];

    protected $hidden = [
        // 'created_at',
        // 'updated_at'
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function subposition(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'sub_position_id')->select('id', 'position_name');
    }

    public function manager(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'manager_id')->select('id', 'first_name', 'last_name');
    }

    public function oldManager(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'old_manager_id')->select('id', 'first_name', 'last_name');
    }

    public function team(): HasOne
    {
        return $this->hasOne(\App\Models\ManagementTeam::class, 'id', 'team_id')->select('id', 'team_name');
    }

    public function oldTeam(): HasOne
    {
        return $this->hasOne(\App\Models\ManagementTeam::class, 'id', 'old_team_id')->select('id', 'team_name');
    }

    public function position(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id')->select('id', 'position_name');
    }

    public function oldPosition(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'old_position_id')->select('id', 'position_name');
    }

    public function subPositionId(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'sub_position_id')->select('id', 'position_name', 'is_selfgen');
    }

    public function oldSubPositionId(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'old_sub_position_id')->select('id', 'position_name');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function product(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }
}
