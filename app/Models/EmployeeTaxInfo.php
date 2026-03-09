<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeTaxInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ssn',
        'document_type',
        'filling_status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
