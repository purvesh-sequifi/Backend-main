<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddOnPlans extends Model
{
    use HasFactory;

    protected $table = 'add_on_plans';

    protected $fillable = [
        'name',
        'rack_price',
        'rack_price_type',
        'discount_type',
        'discount_price',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
