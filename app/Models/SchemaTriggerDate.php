<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchemaTriggerDate extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'schema_trigger_dates';

    protected $fillable = [
        'name',
        'color_code',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
