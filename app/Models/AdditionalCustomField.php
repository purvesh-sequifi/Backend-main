<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalCustomField extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'additional_custom_fields';

    protected $fillable = [
        'configuration_id',
        'type',
        'field_name',
        'field_type',
        'field_required',
        'attribute_option',
        'height_value',
        'is_deleted',
        'scored',
        'attribute_option_rating',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
