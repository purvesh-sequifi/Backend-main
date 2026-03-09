<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessAddress extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'business_address';

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
        'mailing_ein',
        'time_zone',
        'lag',
        'lng',

    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public static function create_Company_Billing_Address()
    {
        $company_profile = CompanyProfile::first();
        $CompanyBillingAddress = [];
        if ($company_profile) {
            $company_profile = $company_profile->toArray();
            $CompanyBillingAddress = new BusinessAddress;
            $CompanyBillingAddress->name = $company_profile['business_name'];
            $CompanyBillingAddress->address = $company_profile['business_address'];
            $CompanyBillingAddress->phone_number = $company_profile['business_phone'];
            $CompanyBillingAddress->company_type = $company_profile['company_type'];
            $CompanyBillingAddress->company_email = $company_profile['company_email'];
            $CompanyBillingAddress->business_name = $company_profile['business_name'];
            $CompanyBillingAddress->mailing_address = $company_profile['business_address_1'];
            $CompanyBillingAddress->business_ein = $company_profile['business_ein'];
            $CompanyBillingAddress->business_phone = $company_profile['business_phone'];
            $CompanyBillingAddress->logo = $company_profile['logo'];
            $CompanyBillingAddress->country = $company_profile['country'];
            $CompanyBillingAddress->time_zone = $company_profile['time_zone'];
            $CompanyBillingAddress->company_website = $company_profile['company_website'];
            $CompanyBillingAddress->business_address = $company_profile['business_address'];
            $CompanyBillingAddress->business_city = $company_profile['business_city'];
            $CompanyBillingAddress->business_state = $company_profile['business_state'];
            $CompanyBillingAddress->business_zip = $company_profile['business_zip'];
            $CompanyBillingAddress->mailing_state = $company_profile['business_state'];
            $CompanyBillingAddress->mailing_city = $company_profile['business_city'];
            $CompanyBillingAddress->mailing_zip = $company_profile['business_zip'];
            $CompanyBillingAddress->mailing_ein = $company_profile['business_ein'];
            $CompanyBillingAddress->save();
            $CompanyBillingAddress->id = 1;
            $CompanyBillingAddress->save();
        }

        return $CompanyBillingAddress;
    }
}
