<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconciliationStatusForSkipedUser extends Model
{
    use HasFactory;

    protected $table = 'reconciliationStatusForSkipedUser';

    protected $fillable = [
        'user_id',
        'office_id',
        'position_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
