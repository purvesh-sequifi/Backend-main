<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HubspotTransectionLog extends Model
{
    use HasFactory;

    protected $table = 'hubspot_transaction_logs';

    protected $fillable = [
        'id',
        'user_id',
        'object_type',
        'api_name',
        'api_url',
        'payload',
        'response',
        'missing_keys', //
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
