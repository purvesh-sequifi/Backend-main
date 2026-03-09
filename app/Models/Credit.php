<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Credit extends Model
{
    use HasFactory;

    protected $table = 'credits';

    protected $fillable = [
        'amount',
        'old_balance_credit',
        'used_credit',
        'balance_credit',
        'month',
        'use_status',

    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
