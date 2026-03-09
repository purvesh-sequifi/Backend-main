<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class M1M2DeductionAlert extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'm1_m2_deduction_alerts';

    protected $fillable = [
        'pid',
        'closer1_id',
        'closer2_id',
        'setter1_id',
        'setter2_id',
        'closer1_m1',
        'closer2_m1',
        'setter1_m1',
        'setter2_m1',
        'closer1_m2',
        'closer2_m2',
        'setter1_m2',
        'setter2_m2',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
