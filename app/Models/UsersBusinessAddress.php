<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersBusinessAddress extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'users_business_address';

    protected $fillable = [
        'user_id',
        'business_address',
        'business_address_line_1',
        'business_address_line_2',
        'business_address_state',
        'business_address_city',
        'business_address_zip',
        'business_address_lat',
        'business_address_long',
        'business_address_timezone',
    ];
}
