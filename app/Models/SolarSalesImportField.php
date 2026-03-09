<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolarSalesImportField extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'solar_sales_import_fields';

    protected $fillable = [
        'name',
        'label',
        'is_mandatory', // 0 = NON MANDATORY, 1 = MANDATORY
        'is_custom', // 0 = NON CUSTOM, 1 = CUSTOM
        'section_name',
        'field_type', // CAN BE DATE, NUMBER ETC.
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
