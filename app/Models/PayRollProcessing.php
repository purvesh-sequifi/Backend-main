<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayRollProcessing extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'emp_payroll_processing';

    protected $fillable = [
        'user_id',
        'commission',
        'overrides',
        'reimbursement',
        'deductions',
        'reconciliation',
        'adjustment',
        'net_pay',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
