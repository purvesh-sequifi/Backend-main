<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestApprovelByPid extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'request_approval_by_pid';

    protected $fillable = [
        'request_id',
        'customer_name',
        'pid',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
