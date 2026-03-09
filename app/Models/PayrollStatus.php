<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollStatus extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'payroll_status';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'status',
    ];
}
