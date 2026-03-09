<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldrouteTransactionLog extends Model
{
    use HasFactory;

    protected $table = 'fieldroute_transaction_log';

    protected $fillable = [
        'id',
        'user_id',
        'ticket_id',
        'api_name',
        'api_url',
        'payload',
        'response',
        'is_processed',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
