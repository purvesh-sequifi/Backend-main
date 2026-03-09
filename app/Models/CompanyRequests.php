<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyRequests extends Model
{
    use HasFactory;

    protected $table = 'company_requests';

    protected $fillable = [
        'company_name',
        'plan_id',
        'full_name',
        'site_name',
        'notes',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
