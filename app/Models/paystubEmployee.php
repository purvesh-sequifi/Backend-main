<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class paystubEmployee extends Model
{
    use HasFactory;

    protected $table = 'paystub_employee';

    protected $fillable = [
        'user_id',
        'user_employee_id',
        'user_first_name',
        'user_middle_name',
        'user_last_name',
        'user_zip_code',
        'user_email',
        'user_work_email',
        'user_home_address',
        'user_position_id',
        'user_social_sequrity_no',
        'user_name_of_bank',
        'user_routing_no',
        'user_account_no',
        'user_type_of_account',
        'pay_period_from',
        'pay_period_to',

        'company_name',
        'company_address',
        'company_website',
        'company_phone_number',
        'company_type',
        'company_email',
        'company_business_name',
        'company_mailing_address',
        'company_business_ein',
        'company_business_phone',
        'company_business_address',
        'company_business_city',
        'company_business_state',
        'company_business_zip',
        'company_mailing_state',
        'company_mailing_city',
        'company_mailing_zip',
        'company_time_zone',
        'company_business_address_1',
        'company_business_address_2',
        'company_business_lat',
        'company_business_long',
        'company_mailing_address_1',
        'company_mailing_address_2',
        'company_mailing_lat',
        'company_mailing_long',
        'company_business_address_time_zone',
        'company_mailing_address_time_zone',
        'company_margin',
        'company_country',
        'company_logo',
        'company_lat',
        'company_lng',
        'user_entity_type',
        'user_business_type',
        'user_business_ein',
        'user_business_name',
        'is_onetime_payment',
        'one_time_payment_id'
        
    ];

    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    public function positionDetailTeam(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'user_position_id');
    }

    public function getUserAccountNoAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }

    public function setUserAccountNoAttribute($value)
    {
        if ($value) {
            if (! isEncrypted($value)) {
                $this->attributes['user_account_no'] = dataEncrypt($value);
            }
        }
    }

    public function getUserRoutingNoAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }

    public function setUserRoutingNoAttribute($value)
    {
        if ($value) {
            if (! isEncrypted($value)) {
                $this->attributes['user_routing_no'] = dataEncrypt($value);
            }
        }
    }

    public function getUserSocialSequrityNoAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }

    public function setUserSocialSequrityNoAttribute($value)
    {
        if ($value) {
            if (! isEncrypted($value)) {
                $this->attributes['user_social_sequrity_no'] = dataEncrypt($value);
            }
        }
    }

    public function getUserBusinessEinAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }

    public function setUserBusinessEinAttribute($value)
    {
        if ($value) {
            if (! isEncrypted($value)) {
                $this->attributes['user_business_ein'] = dataEncrypt($value);
            }
        }
    }

    public function getCompanyBusinessEinAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }

    public function setCompanyBusinessEinAttribute($value)
    {
        if ($value) {
            if (! isEncrypted($value)) {
                $this->attributes['company_business_ein'] = dataEncrypt($value);
            }
        }
    }
}
