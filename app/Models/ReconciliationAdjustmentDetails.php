<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconciliationAdjustmentDetails extends Model
{
    use HasFactory;

    protected $table = 'reconciliations_adjustement_details';

    protected $fillable = [
        'user_id',
        'pid',
        'start_date',
        'end_date',
        'amount',
        'type',
        'adjustment_type',
        'comment',
        'comment_by',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->with('recruiter');
    }

    public function commentUser(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'comment_by');
    }
}
