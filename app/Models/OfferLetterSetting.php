<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferLetterSetting extends Model
{
    use HasFactory;

    protected $table = 'offer_letter_setting';

    protected $fillable = [
        'offer_letter_id',
        'field_name',
        'field_type',
        'field_required',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
