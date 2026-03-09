<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingType extends Model
{
    use HasFactory;

    protected $table = 'billing_type';

    protected $fillable = [
        'name',
        'frequency',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
