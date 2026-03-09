<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvancePaymentSetting extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'adwance_payment_settings';

    protected $fillable = [
        'adwance_setting',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
