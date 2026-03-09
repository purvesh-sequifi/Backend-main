<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherImportantLog extends Model
{
    use HasFactory;

    protected $table = 'other_important_logs';

    protected $fillable = [
        'user_id',
        'ApiName',
        'response_data',
        'other_data',
    ];
}
