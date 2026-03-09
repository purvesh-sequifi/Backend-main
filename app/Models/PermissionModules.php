<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionModules extends Model
{
    use HasFactory;

    protected $table = 'permission_modules';

    protected $fillable = [
        'module',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function subModule(): HasMany
    {
        return $this->hasMany(PermissionTabs::class, 'module_id', 'id')->select('id', 'module_tab', 'module_id')->with('permissionSubModule');
    }
}
