<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalInfoForEmployeeToGetStarted extends Model
{
    use HasFactory;
    use SpatieLogsActivity;

    protected $table = 'additional_info_for_employee_to_get_started';

    protected $fillable = [
        'configuration_id',
        'field_name',
        'field_type',
        'field_required',
        'attribute_option',
        'is_deleted',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
