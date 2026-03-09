<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeResponseLog extends Model
{
    use HasFactory;

    protected $table = 'stripe_response_logs';

    protected $fillable = [
        'id',
        'user_id',
        'response',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
