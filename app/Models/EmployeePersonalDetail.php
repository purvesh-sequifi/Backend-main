<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePersonalDetail extends Model
{
    use HasFactory;
    use SpatieLogsActivity;

    protected $table = 'employee_personal_detail';

    protected $fillable = [
        'configuration_id',
        'field_name',
        'field_type',
        'field_required',
        'attribute_option',
        'height_value',
        'is_deleted',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
