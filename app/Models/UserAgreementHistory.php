<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAgreementHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_agreement_histories';

    protected $fillable = [
        'user_id',
        'updater_id',
        'probation_period',
        'old_probation_period',
        'offer_include_bonus',
        'old_offer_include_bonus',
        'hiring_bonus_amount',
        'old_hiring_bonus_amount',
        'date_to_be_paid',
        'old_date_to_be_paid',
        'period_of_agreement',
        'old_period_of_agreement',
        'end_date',
        'old_end_date',
        'offer_expiry_date',
        'old_offer_expiry_date',
        'hired_by_uid',
        'old_hired_by_uid',
        'hiring_signature',
        'old_hiring_signature',
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function hiringby(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'hired_by_uid')->select('id', 'first_name', 'last_name');
    }

    public function old_hiringby(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'old_hired_by_uid')->select('id', 'first_name', 'last_name');
    }
}
