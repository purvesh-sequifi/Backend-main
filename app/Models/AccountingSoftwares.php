<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingSoftwares extends Model
{
    use HasFactory;

    protected $table = 'accounting_softwares';

    protected $fillable = [
        'name',
        'logo',
    ];
}
