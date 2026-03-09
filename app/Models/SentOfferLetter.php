<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SentOfferLetter extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'sent_offer_letters';

    protected $fillable = [
        'template_id',
        'onboarding_employee_id',
    ];
}
