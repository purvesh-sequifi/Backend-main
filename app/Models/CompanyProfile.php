<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    use HasFactory;
    use SpatieLogsActivity;

    protected $table = 'company_profiles';

    const PEST_COMPANY_TYPE = ['Pest', 'Fiber'];

    const SOLAR_COMPANY_TYPE = 'Solar';

    const FIBER_COMPANY_TYPE = 'Fiber';

    const TURF_COMPANY_TYPE = 'Turf';

    const ROOFING_COMPANY_TYPE = 'Roofing';

    const MORTGAGE_COMPANY_TYPE = 'Mortgage';

    const SOLAR2_COMPANY_TYPE = 'Solar2';

    protected $method = 'AES-256-CBC';

    protected $key = 0;

    protected $iv = '1234567891011121';

    protected $fillable = [
        'name',

        'address',
        'phone_number',
        'company_type',
        'address',
        'company_email',
        'business_name',
        'mailing_address',
        'business_ein',
        'business_phone',
        'logo',
        'country',
        'time_zone',
        'company_website',
        'business_address',
        'business_city',
        'business_state',
        'business_zip',
        'mailing_state',
        'mailing_city',
        'mailing_zip',
        'time_zone',
        'lag',
        'lng',
        'business_address_1',
        'business_address_2',
        'business_lat',
        'business_long',
        'mailing_address_1',
        'mailing_address_2',
        'mailing_lat',
        'mailing_long',
        'business_address_time_zone',
        'mailing_address_time_zone',
        'company_margin',
        'deduct_any_available_reconciliation_upfront',
        'frequency_type_id', // billing frequency type
        'setup_status', // Track company setup status
        'setup_completed_at', // When setup was completed
        'setup_error', // Error message if setup failed
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function getBusinessEinAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }

    public function setBusinessEinAttribute($value)
    {
        if ($value) {
            if (! isEncrypted($value)) {
                $this->attributes['business_ein'] = dataEncrypt($value);
            }
        }
    }
}
