<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadCustomFieldSetting extends Model
{
    use HasFactory;

    protected $table = 'lead_custom_field_setting';

    protected $fillable = [
        'user_id',
        'lead_custom_field_setting',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
