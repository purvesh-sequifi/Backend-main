<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;
use Spatie\Activitylog\Models\Activity;

class payFrequencySetting extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'pay_frequency_setting';

    protected $fillable = [
        'frequency_type_id',
        'first_months',
        'first_day',
        'day_of_week',
        'day_of_months',
        'pay_period',
        'monthly_pay_type',
        'monthly_per_days',
        'first_day_pay_of_manths',
        'second_pay_day_of_month',
        'deadline_to_run_payroll',
        'first_pay_period_ends_on',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected static $frequencyTypeName;

    // Setter for custom field
    public function setCustomField($value)
    {
        self::$frequencyTypeName = $value;
    }

    public function tapActivity(Activity $activity)
    {// Custom property for activity log
        $existingProperties = $activity->properties->toArray();
        $oldValues = $activity->subject->toArray();
        $frequencyTypeName = self::$frequencyTypeName;
        $newProperties = ['frequency_type' => $frequencyTypeName];
        $existingProperties['attributes']['frequency_type'] = $frequencyTypeName;
        $existingProperties['old']['frequency_type'] = $frequencyTypeName;
        $activity->properties = $existingProperties;
    }
}
