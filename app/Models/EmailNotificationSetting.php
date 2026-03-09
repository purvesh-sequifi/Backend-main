<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailNotificationSetting extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'email_notification_settings';

    protected $fillable = [
        'company_id',
        'status',
        'email_setting_type',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
