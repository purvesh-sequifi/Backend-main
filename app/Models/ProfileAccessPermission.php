<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileAccessPermission extends Model
{
    use HasFactory;

    protected $table = 'profile_access_permissions';

    protected $fillable = [
        'group_id',
        'role_id',
        'group_policies_id',
        'position_id',
        'profile_access_for',
        'payroll_history',
        'reset_password',
        'type',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
