<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollProcess extends Model
{
    use HasFactory;

    protected $table = 'payroll_processes';

    protected $fillable = [
        'name',
        'logo',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
