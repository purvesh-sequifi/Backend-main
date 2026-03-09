<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This table holds settings
 * for all custom lead forms.
 * This is kind of a common settings for all custom lead forms
 */
class CustomLeadFormGlobalSetting extends Model
{
    use HasFactory;

    protected $table = 'custom_lead_form_global_settings';

    protected $fillable = [
        'rating_status',
    ];

    public const STATUS_ENABLED = 1;

    public const STATUS_DISABLED = 0;

    // public function getRatingStatusAttribute($val)
    // {
    //     return $val === self::STATUS_ENABLED ? 'Enabled' : 'Disabled';
    // }

}
