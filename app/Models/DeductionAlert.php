<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeductionAlert extends Model
{
    use HasFactory;

    protected $table = 'deduction_alerts';

    protected $fillable = [
        'pid',
        'user_id',
        'position_id',
        'amount',
        'status',
        'action_status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function users(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'email');
    }
}
