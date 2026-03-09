<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StateMVRCost extends Model
{
    use HasFactory;

    protected $table = 'state_mvr_costs';

    protected $fillable = [
        'id',
        'name',
        'state_code',
        'cost',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
