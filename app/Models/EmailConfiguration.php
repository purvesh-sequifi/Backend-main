<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailConfiguration extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'email_configuration';

    protected $fillable = [
        'from_email_address',
        'service_provider',
        'host_mailer',
        'host_name ',
        'smtp_port',
        'timeout',
        'security_protocol',
        'authentication_method',
        'token_app_id',
        'token_app_key',
        'user_name',
        'password',
    ];
}
