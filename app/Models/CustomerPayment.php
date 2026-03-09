<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    use HasFactory;

    protected $table = 'customer_payments';

    protected $fillable = ['pid', 'customer_payment_json'];

    protected $casts = [
        'customer_payment_json' => 'array',
    ];
}
