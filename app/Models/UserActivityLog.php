<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    use HasFactory;

    protected $table = 'user_activity_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'page',
        'action',
        'description',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
