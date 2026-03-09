<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FineFee extends Model
{
    use HasFactory;

    protected $table = 'fine_fees';

    protected $fillable = [
        'employee_id',
        'type',
        'amount',
        'date',
        'description',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'employee_id')->select('id', 'first_name', 'last_name', 'image');
    }
}
