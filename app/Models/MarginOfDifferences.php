<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarginOfDifferences extends Model
{
    use HasFactory;

    protected $table = 'margin_of_differences';

    protected $fillable = [
        'difference_parcentage',
        'applied_to',
        'margin_setting_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
