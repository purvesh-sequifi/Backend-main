<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCommissions extends Model
{
    use HasFactory;

    protected $table = 'sub_commissions';

    protected $fillable = [
        // 'name',
        'company_name',
        'commissions_id',
    ];
}
