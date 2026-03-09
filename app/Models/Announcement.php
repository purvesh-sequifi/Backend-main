<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Announcement extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'announcements';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'positions',
        'office',
        'link',
        'start_date',
        'durations',
        'file',
        'end_date',
        'pin_to_top',
        'disable',
        'file',

    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'positions');
    }

    public function officeDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'office');
    }
}
