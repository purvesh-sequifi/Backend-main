<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserDepartmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'effective_date',
        'department_id',
        'updater_id',
        'old_department_id',
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

    public function department(): HasOne
    {
        return $this->hasOne(\App\Models\Department::class, 'id', 'department_id')->select('id', 'name');
    }

    public function oldDepartment(): HasOne
    {
        return $this->hasOne(\App\Models\Department::class, 'id', 'old_department_id')->select('id', 'name');
    }
}
