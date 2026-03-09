<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDismissHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_dismiss_histories';

    const DISMISSED = 1;

    const NON_DISMISSED = 0;

    protected $fillable = [
        'user_id',
        'dismiss', // 0: Not Dismissed, 1: Dismissed
        'effective_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
