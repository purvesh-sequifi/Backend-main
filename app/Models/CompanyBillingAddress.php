<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyBillingAddress extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'company_billing_addresses';

    protected $fillable = [
        'company_website',
        'company_email',
        'business_name',
        'business_ein',
        'business_phone',
        'business_address',
        'business_address_1',
        'business_address_2',
        'country',
        'business_state',
        'business_city',
        'business_zip',
        'business_address_time_zone',
        'business_lat',
        'business_long',
    ];

    protected $hidden = [
        'created_at',
    ];

    protected $method = 'AES-256-CBC';

    protected $key = 0;

    protected $iv = '1234567891011121';

    public static function create_Company_Billing_Address()
    {
        $company_profile = CompanyProfile::first();

        $CompanyBillingAddress = [];
        if ($company_profile) {
            $company_profile = $company_profile->toArray();
            $CompanyBillingAddress = new CompanyBillingAddress;
            $CompanyBillingAddress->company_website = $company_profile['company_website'];
            $CompanyBillingAddress->company_email = $company_profile['company_email'];
            $CompanyBillingAddress->business_name = $company_profile['business_name'];
            $CompanyBillingAddress->business_ein = $company_profile['business_ein'];
            $CompanyBillingAddress->business_phone = $company_profile['business_phone'];
            $CompanyBillingAddress->business_address = $company_profile['business_address'];
            $CompanyBillingAddress->business_address_1 = $company_profile['business_address_1'];
            $CompanyBillingAddress->business_address_2 = $company_profile['business_address_2'];
            $CompanyBillingAddress->country = $company_profile['country'];
            $CompanyBillingAddress->business_state = $company_profile['business_state'];
            $CompanyBillingAddress->business_city = $company_profile['business_city'];
            $CompanyBillingAddress->business_zip = $company_profile['business_zip'];
            $CompanyBillingAddress->business_address_time_zone = $company_profile['business_address_time_zone'];
            $CompanyBillingAddress->business_lat = $company_profile['business_lat'];
            $CompanyBillingAddress->business_long = $company_profile['business_long'];
            $CompanyBillingAddress->save();
            $CompanyBillingAddress->id = 1;
            $CompanyBillingAddress->save();
        }

        return $CompanyBillingAddress;
    }

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
