<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTerminateHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $fillable = ['user_id', 'terminate_effective_date', 'is_terminate'];

    const TERMINATED = 1;

    const NON_TERMINATED = 0;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
