<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollObserversLog extends Model
{
    use HasFactory;

    protected $table = 'payroll_observers_logs';

    protected $fillable = [
        'payroll_id',
        'action',
        'observer',
        'old_value',
        'error'
    ];
}
