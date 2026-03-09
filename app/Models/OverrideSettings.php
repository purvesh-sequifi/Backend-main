<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverrideSettings extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'override__settings';

    protected $fillable = [
        'settlement_type',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
