<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterigationTransactionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'interigation_name',
        'api_name',
        'payload',
        'response',
        'url',
    ];
}
