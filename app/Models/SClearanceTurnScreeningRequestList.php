<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SClearanceTurnScreeningRequestList extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 's_clearance_turn_screening_request_lists';

    protected $fillable = [
        'id',
        'email',
        'first_name',
        'middle_name',
        'no_middle_name',
        'last_name',
        'state',
        'user_type',
        'user_type_id',
        'position_id',
        'office_id',
        'description',
        'zipcode',
        'package_id',
        'turn_id',
        'worker_id',
        'date_sent',
        'report_date',
        'is_report_generated',
        'approved_declined_by',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }
}
