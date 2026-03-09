<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    use SpatieLogsActivity;

    protected $fillable = [
        'user_id',
        'description',
        'type',
        'is_read',
    ];
}
