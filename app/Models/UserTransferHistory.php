<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserTransferHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_transfer_history';

    protected $fillable = [
        'user_id',
        'transfer_effective_date',
        'effective_end_date',
        'updater_id',
        'state_id',
        'old_state_id',
        'office_id',
        'old_office_id',
        'department_id',
        'old_department_id',
        'position_id',
        'old_position_id',
        'sub_position_id',
        'old_sub_position_id',
        'is_manager',
        'old_is_manager',
        'self_gen_accounts',
        'old_self_gen_accounts',
        'manager_id',
        'old_manager_id',
        'team_id',
        'old_team_id',
        'redline',
        'redline_amount_type',
        'old_redline_amount_type',
        'old_redline',
        'redline_type',
        'old_redline_type',
        'self_gen_redline_amount_type',
        'old_self_gen_redline_amount_type',
        'self_gen_redline',
        'old_self_gen_redline',
        'self_gen_redline_type',
        'old_self_gen_redline_type',
        'existing_employee_new_manager_id',
        'existing_employee_old_manager_id',
    ];

    protected $hidden = [
        'created_at',
        // 'updated_at'
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'image');
    }

    public function positions(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'sub_position_id');
    }

    public function office(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'office_id');
    }

    public function state(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'state_id');
    }

    public function oldPositions(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'old_sub_position_id');
    }

    public function oldOffice(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'old_office_id');
    }

    public function oldState(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'old_state_id');
    }

    public function manager(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'manager_id');
    }

    public function oldManager(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'old_manager_id');
    }

    public function department(): HasOne
    {
        return $this->hasOne(\App\Models\Department::class, 'id', 'department_id')->select('id', 'name');
    }

    public function oldDepartment(): HasOne
    {
        return $this->hasOne(\App\Models\Department::class, 'id', 'old_department_id')->select('id', 'name');
    }

    public function position(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id')->select('id', 'position_name');
    }

    public function oldPosition(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'old_position_id')->select('id', 'position_name');
    }

    public function subposition(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'sub_position_id')->select('id', 'position_name');
    }

    public function oldSubPosition(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'old_sub_position_id')->select('id', 'position_name');
    }

    public function userInfo(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }
}
