<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestData extends Model
{
    use HasFactory;

    protected $table = 'test_data';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'description',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
