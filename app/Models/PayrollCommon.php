<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollCommon extends Model
{
    use HasFactory;

    protected $table = 'payroll_common';

    protected $fillable = [
        'orig_payfrom',
        'orig_payto',
        'payroll_modified_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
