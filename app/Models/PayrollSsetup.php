<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSsetup extends Model
{
    protected $table = 'payroll_setups';
    use HasFactory, SpatieLogsActivity;
    protected $fillable = [
        'id',
        'field_name',
        'worked_type',
        'payment_type',
        'status'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];
}
