<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobNotification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'job_id',
        'job_type',
        'job_name',
        'status',
        'progress',
        'message',
        'metadata',
        'company_profile_id',
        'domain_name',
        'user_id',
        'session_key',
        'initiated_at',
        'completed_at',
        'duration_seconds',
        'estimated_duration_seconds',
        'records_processed',
        'records_per_second',
        'memory_peak_mb',
        'file_url',
        'file_size_kb',
        'error_message',
        'error_file',
        'error_line',
    ];

    protected $casts = [
        'metadata' => 'array',
        'progress' => 'integer',
        'company_profile_id' => 'integer',
        'user_id' => 'integer',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'integer',
        'estimated_duration_seconds' => 'integer',
        'records_processed' => 'integer',
        'records_per_second' => 'decimal:2',
        'memory_peak_mb' => 'decimal:2',
        'file_size_kb' => 'decimal:2',
        'error_line' => 'integer',
    ];

    /**
     * Scope: Get recent notifications (for polling fallback)
     */
    public function scopeRecent($query, int $minutes = 5)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope: Get by session key
     */
    public function scopeForSession($query, string $sessionKey)
    {
        return $query->where('session_key', $sessionKey);
    }

    /**
     * Scope: Get by user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Get by company
     */
    public function scopeForCompany($query, int $companyProfileId)
    {
        return $query->where('company_profile_id', $companyProfileId);
    }

    /**
     * Scope: Active jobs (not completed/failed)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['started', 'processing']);
    }

    /**
     * Check if job is still running
     */
    public function isRunning(): bool
    {
        return in_array($this->status, ['started', 'processing']);
    }

    /**
     * Check if job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if job failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get time accuracy percentage
     */
    public function getTimeAccuracyAttribute(): ?float
    {
        if (!$this->duration_seconds || !$this->estimated_duration_seconds) {
            return null;
        }

        $actual = $this->duration_seconds;
        $estimated = $this->estimated_duration_seconds;

        return round((min($actual, $estimated) / max($actual, $estimated)) * 100, 2);
    }

    /**
     * Relationship: User who initiated the job
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Company profile
     */
    public function companyProfile()
    {
        return $this->belongsTo(CompanyProfile::class);
    }
}

