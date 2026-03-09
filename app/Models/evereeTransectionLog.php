<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class evereeTransectionLog extends Model
{
    use HasFactory;

    protected $table = 'everee_transections_log';

    protected $fillable = [
        'id',
        'user_id',
        'api_name',
        'api_url',
        'payload',
        'response',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
