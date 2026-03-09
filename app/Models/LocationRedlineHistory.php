<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LocationRedlineHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'location_redline_history';

    protected $fillable = [
        'location_id',
        'redline_min',
        'redline_standard',
        'redline_max',
        'created_by',
        'updated_by',
        'effective_date',

    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function createdBy(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'created_by')->select('id', 'first_name', 'last_name', 'image');
    }

    public function updatedBy(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updated_by')->select('id', 'first_name', 'last_name', 'image');
    }
}
