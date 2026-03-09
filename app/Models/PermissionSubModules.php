<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermissionSubModules extends Model
{
    use HasFactory;

    protected $table = 'modules_with_permission';

    protected $fillable = [
        'module_id',
        'module_tab_id',
        'submodule',
        'action',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
