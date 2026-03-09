<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SClearanceTransunionResponse extends Model
{
    use HasFactory;

    protected $table = 's_clearance_transunion_responses';

    protected $fillable = [
        'id',
        'screening_request_applicant_id',
        'status',
        'is_manual_verification',
        'response',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
