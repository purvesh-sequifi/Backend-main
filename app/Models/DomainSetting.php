<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class DomainSetting extends Model
{
    use HasFactory;
    use SpatieLogsActivity;

    protected $table = 'domain_settings';

    protected $fillable = [
        'id',
        'domain_name',
        'status',
        'email_setting_type',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // check_domain_setting send email allow or not
    public static function check_domain_setting($email)
    {
        $to_email = $email;
        $emailId = explode('@', $to_email);
        $emailSetting = EmailNotificationSetting::where('company_id', '1')->where('status', '1')->first();
        $status = false;
        $message = "Domain setting isn't allowed to send e-mail on this domain.";
        if ($emailSetting != '') {
            $domain_settings_count = DomainSetting::where('domain_name', $emailId[1])->where('status', 1)->count();
            // sending mail if allow to all mail domain are domain setting is active
            if ($emailSetting->email_setting_type == 1 || $domain_settings_count > 0) {
                $status = true;
                $message = 'Domain setting allowed to send e-mail on this domain.';
            }
        }

        return $response = [
            'status' => $status,
            'message' => $message,
        ];
    }

    public function tapActivity(Activity $activity)
    {// Custom property for activity log
        $existingProperties = $activity->properties->toArray();
        $oldValues = $activity->subject->toArray();
        $newProperties = ['setting_type' => ucfirst(@$oldValues['domain_name'])];
        $activity->properties = array_merge($existingProperties, $newProperties);
    }
}
