<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchProcessTracker extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'process_type',
        'status',
        'total_records',
        'processed_records',
        'success_count',
        'error_count',
        'user_id',
        'started_at',
        'completed_at',
        'stats',
        'metadata',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'stats' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the user who initiated this process.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
