<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionTabs extends Model
{
    use HasFactory;

    protected $table = 'permission_submodules';

    protected $fillable = [
        'module_id',
        'module_tab',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function permissionSubModule(): HasMany
    {
        return $this->hasMany(PermissionsubModules::class, 'module_tab_id', 'id')->select('id', 'module_tab_id', 'submodule', 'action');
    }
}
