<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SClearanceScreeningRequestList extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 's_clearance_screening_request_lists';

    protected $fillable = [
        'id',
        'email',
        'first_name',
        'middle_name',
        'last_name',
        'user_type',
        'user_type_id',
        'position_id',
        'office_id',
        'description',
        'applicant_id',
        'screening_request_id',
        'screening_request_applicant_id',
        'exam_id',
        'is_manual_verification',
        'is_report_generated',
        'date_sent',
        'report_date',
        'approved_declined_by',
        'status',
        'exam_attempts',
        'plan_id',
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
