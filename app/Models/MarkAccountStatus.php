<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarkAccountStatus extends Model
{
    use HasFactory;

    protected $table = 'mark_account_status';

    protected $fillable = [
        'account_status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
