<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAlertHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'payment_alert_histories';

    protected $fillable = [
        'payroll_id',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
