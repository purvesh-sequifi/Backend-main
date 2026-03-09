<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SClearanceStatus extends Model
{
    use HasFactory;

    protected $table = 's_clearance_statuses';

    protected $fillable = [
        'id',
        'status_name',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
