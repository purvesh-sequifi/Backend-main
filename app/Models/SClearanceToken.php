<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SClearanceToken extends Model
{
    use HasFactory;

    protected $table = 's_clearance_tokens';

    protected $fillable = [
        'id',
        'token',
        'mfa_token',
        'token_key_used',
        'mfa_token_key_used',
        'expiration_time',
        'mfa_expiration_time',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
