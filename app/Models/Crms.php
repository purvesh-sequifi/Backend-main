<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
/**
 * status = 0 inactive
 * status = 1 Active
 * enable_for_w2 = 0 disable
 * enable_for_w2 = 1 enabe
 */
class Crms extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'crms';

    protected $fillable = [
        'name',
        'logo',
        'status',
        'enable_for_w2',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function crmSetting(): HasOne
    {
        return $this->hasOne(CrmSetting::class, 'crm_id');
    }
}
