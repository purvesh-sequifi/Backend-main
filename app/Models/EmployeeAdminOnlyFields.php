<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAdminOnlyFields extends Model
{
    use HasFactory;
    use SpatieLogsActivity;

    protected $table = 'employee_admin_only_fields';

    protected $fillable = [
        'configuration_id',
        'field_name',
        'field_type',
        'field_required',
        'attribute_option',
        'field_permission',
        'field_data_entry',
        'is_deleted',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
