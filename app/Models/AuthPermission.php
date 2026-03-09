<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthPermission extends Model
{
    use HasFactory;

    protected $table = 'auth_permission';

    protected $fillable = [
        'name',
        'content_type_id',
        'codename',
    ];
}
