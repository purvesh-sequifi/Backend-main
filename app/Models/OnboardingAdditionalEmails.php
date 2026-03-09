<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingAdditionalEmails extends Model
{
    use HasFactory;

    protected $table = 'onboarding_additional_emails';

    protected $fillable = [
        'onboarding_user_id',
        'email',
    ];

    protected $hidden = [
        'created_at',
    ];
}
