<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDataUpdateLogs extends Model
{
    use HasFactory;

    protected $table = 'sale_data_update_logs';

    protected $fillable = [
        'pid',
        'message_text',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
