<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOverrideQueue extends Model
{
    use HasFactory;

    protected $table = 'user_overrides_queue';

    protected $fillable = [
        'user_id',
        'processing',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
