<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveToReconHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'type_id',
        'pid',
        'user_id',
        'pay_period_from',
        'pay_period_to',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function moveToReconClawback(): BelongsTo
    {
        return $this->belongsTo(new ClawbackSettlement, 'type_id', 'id')->where('type', 'clawback');
    }
}
