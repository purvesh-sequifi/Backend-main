<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SClearanceTurnResponse extends Model
{
    use HasFactory;

    protected $table = 's_clearance_turn_responses';

    protected $fillable = [
        'id',
        'turn_id',
        'worker_id',
        'webhook_type',
        'status',
        'response',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
