<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadDocument extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    // status
    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    public const STATUS_ARCHIVED = 2;

    public static $statusDescriptions = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    protected $fillable = [
        'user_id',
        'lead_id',
        'path',
        'status',
    ];

    protected $appends = ['status_description'];

    /**
     * Define the relationship to the Lead model.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function getStatusDescriptionAttribute(): string
    {
        return self::$statusDescriptions[$this->status] ?? 'Unknown';
    }
}
