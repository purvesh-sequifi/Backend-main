<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SClearanceConfiguration extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 's_clearance_configurations';

    protected $fillable = [
        'id',
        'position_id',
        'hiring_status',
        'is_mandatory',
        'is_approval_required',
        'package_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function statusDetail(): HasOne
    {
        return $this->hasOne(\App\Models\HiringStatus::class, 'id', 'hiring_status');
    }

    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }
}
