<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class AutomationActionLog extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'automation_action_logs';

    protected $fillable = [
        'automation_rule_id',
        'lead_id',
        'onboarding_id',
        'sub_task_id',
        'old_pipeline_lead_status',
        'new_pipeline_lead_status',
        'from_status_id',
        'to_status_id',
        'context_hash',
        'trigger_context',
        'event',
        'category',
        'status',
        'trace_log',
        'email',
        'email_sent',
        'is_new_contract',
        'context_type',
    ];

    protected $casts = [
        'trigger_context' => 'array',
        // 'old_pipeline_lead_status' => 'array',
        // 'new_pipeline_lead_status' => 'array',
    ];

    /**
     * Create or find existing AutomationActionLog with context-aware duplicate prevention
     */
    public static function createSafely(array $attributes): AutomationActionLog
    {
        // Generate context hash for intelligent duplicate prevention
        $contextHash = self::generateContextHash($attributes);
        $attributes['context_hash'] = $contextHash;

        // Check for existing identical context (true duplicate)
        $existingLog = self::where('context_hash', $contextHash)
            ->where('email_sent', 1)
            ->whereNull('deleted_at')
            ->first();

        if ($existingLog) {
            Log::info('AutomationActionLog: Duplicate prevented', [
                'existing_log_id' => $existingLog->id,
                'context_hash' => $contextHash,
            ]);

            return $existingLog;
        }

        // Check for failed automation with same context that can be retried
        $failedLog = self::where('context_hash', $contextHash)
            ->where('email_sent', 0)
            ->whereNull('deleted_at')
            ->first();

        if ($failedLog) {
            Log::info('AutomationActionLog: Retrying failed automation', [
                'log_id' => $failedLog->id,
            ]);

            $failedLog->update($attributes);

            return $failedLog;
        }

        // Create new log - this is a legitimate different context
        $log = self::create($attributes);

        Log::info('AutomationActionLog: New automation created', [
            'log_id' => $log->id,
        ]);

        return $log;
    }

    /**
     * Generate unique context hash for duplicate prevention
     */
    private static function generateContextHash(array $attributes): string
    {
        // Create unique context based on actual automation trigger factors
        $contextData = [
            'automation_rule_id' => $attributes['automation_rule_id'] ?? null,
            'lead_id' => $attributes['lead_id'] ?? null,
            'onboarding_id' => $attributes['onboarding_id'] ?? null,
            'from_status_id' => $attributes['from_status_id'] ?? null,
            'to_status_id' => $attributes['to_status_id'] ?? null,
            'event' => $attributes['event'] ?? null,
            'category' => $attributes['category'] ?? null,
            'is_new_contract' => $attributes['is_new_contract'] ?? null, // Include contract context
            'date' => now()->format('Y-m-d'), // Same day context grouping
        ];

        // Remove null values and create hash
        $contextData = array_filter($contextData, fn ($value) => $value !== null);

        return hash('sha256', json_encode($contextData, 64)); // JSON_SORT_KEYS = 64
    }

    /**
     * Check if a similar log entry already exists
     */
    public static function isDuplicate(array $criteria): bool
    {
        $query = self::whereNull('deleted_at');

        foreach ($criteria as $field => $value) {
            if (! is_null($value)) {
                $query->where($field, $value);
            }
        }

        return $query->exists();
    }
}
