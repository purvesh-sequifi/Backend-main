<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permissions extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'permissions';

    protected $fillable = [
        'policies_tabs_id',
        'name',
        'guard_name',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
