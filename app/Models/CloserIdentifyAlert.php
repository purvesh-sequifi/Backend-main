<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CloserIdentifyAlert extends Model
{
    use HasFactory;

    protected $table = 'closer_identify_alert';

    protected $fillable = [
        'pid',
        'sales_rep_email',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
