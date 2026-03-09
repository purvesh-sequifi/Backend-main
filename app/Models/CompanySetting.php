<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class CompanySetting extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'company_settings';

    protected $fillable = [
        'type',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tapActivity(Activity $activity)
    {// Custom property for activity log
        $existingProperties = $activity->properties->toArray();
        $oldValues = $activity->subject->toArray();
        $newProperties = ['setting_type' => ucfirst(@$oldValues['type'])];
        $activity->properties = array_merge($existingProperties, $newProperties);
    }
}
