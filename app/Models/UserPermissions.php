<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermissions extends Model
{
    use HasFactory;

    protected $table = 'user_permissions';

    protected $fillable = [
        'position_id',
        'module_id',
        'sub_module_id',
        'parmission_id',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function permissionModule(): BelongsTo
    {
        return $this->belongsTo(PermissionModules::class, 'module_id', 'id');
    }

    public function permissionTab(): BelongsTo
    {
        return $this->belongsTo(PermissionTabs::class, 'tab_id', 'id');
    }

    public function permissionSubModule(): BelongsTo
    {
        return $this->belongsTo(PermissionSubModules::class, 'sub_module_id', 'id');
    }
}
