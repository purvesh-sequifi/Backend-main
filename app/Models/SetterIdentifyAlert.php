<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SetterIdentifyAlert extends Model
{
    use HasFactory;

    protected $table = 'setter_identify_alert';

    protected $fillable = [
        'pid',
        'sales_rep_email',
        'setter_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
