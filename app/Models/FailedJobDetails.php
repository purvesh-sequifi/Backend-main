<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedJobDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'failed_job_uuid',
        'job_id',
        'job_class',
        'queue',
        'connection',
        'failure_reason',
        'stack_trace',
        'payload_data',
        'context_data',
        'memory_usage_mb',
        'peak_memory_mb',
        'execution_time_ms',
        'worker_pid',
        'php_version',
        'server_info',
        'attempts',
        'max_tries',
        'timeout',
        'first_failed_at',
        'last_failed_at',
        'error_type',
        'error_category',
        'is_retryable',
        'resolution_notes',
        'related_job_performance_log_id',
    ];

    protected $casts = [
        'payload_data' => 'array',
        'context_data' => 'array',
        'first_failed_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'is_retryable' => 'boolean',
        'memory_usage_mb' => 'decimal:2',
        'peak_memory_mb' => 'decimal:2',
    ];

    // Error type constants
    const ERROR_TYPE_DATABASE = 'database';

    const ERROR_TYPE_TIMEOUT = 'timeout';

    const ERROR_TYPE_MEMORY = 'memory';

    const ERROR_TYPE_EXCEPTION = 'exception';

    const ERROR_TYPE_NETWORK = 'network';

    const ERROR_TYPE_AUTHENTICATION = 'authentication';

    const ERROR_TYPE_VALIDATION = 'validation';

    const ERROR_TYPE_EXTERNAL_API = 'external_api';

    const ERROR_TYPE_FILE_SYSTEM = 'file_system';

    const ERROR_TYPE_UNKNOWN = 'unknown';

    // Error category constants
    const ERROR_CATEGORY_RECOVERABLE = 'recoverable';

    const ERROR_CATEGORY_PERMANENT = 'permanent';

    const ERROR_CATEGORY_CONFIGURATION = 'configuration';

    const ERROR_CATEGORY_RESOURCE = 'resource';

    const ERROR_CATEGORY_BUSINESS_LOGIC = 'business_logic';

    /**
     * Get the failed job that this detail belongs to
     */
    public function failedJob(): BelongsTo
    {
        return $this->belongsTo(FailedJob::class, 'failed_job_uuid', 'uuid');
    }

    /**
     * Get the related job performance log if exists
     */
    public function jobPerformanceLog(): BelongsTo
    {
        return $this->belongsTo(JobPerformanceLog::class, 'related_job_performance_log_id');
    }

    /**
     * Scope to filter by error type
     */
    public function scopeByErrorType($query, $errorType)
    {
        return $query->where('error_type', $errorType);
    }

    /**
     * Scope to filter by error category
     */
    public function scopeByErrorCategory($query, $errorCategory)
    {
        return $query->where('error_category', $errorCategory);
    }

    /**
     * Scope to filter by retryable status
     */
    public function scopeRetryable($query)
    {
        return $query->where('is_retryable', true);
    }

    /**
     * Scope to filter by non-retryable status
     */
    public function scopeNonRetryable($query)
    {
        return $query->where('is_retryable', false);
    }

    /**
     * Scope to filter by job class
     */
    public function scopeByJobClass($query, $jobClass)
    {
        return $query->where('job_class', $jobClass);
    }

    /**
     * Scope to filter by queue
     */
    public function scopeByQueue($query, $queue)
    {
        return $query->where('queue', $queue);
    }

    /**
     * Scope for recent failures (last 24 hours)
     */
    public function scopeRecent($query)
    {
        return $query->where('last_failed_at', '>=', Carbon::now()->subDay());
    }

    /**
     * Scope for failures within date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('last_failed_at', [$startDate, $endDate]);
    }

    /**
     * Get formatted execution time
     */
    public function getFormattedExecutionTimeAttribute()
    {
        if (! $this->execution_time_ms) {
            return null;
        }

        if ($this->execution_time_ms < 1000) {
            return $this->execution_time_ms.'ms';
        }

        return round($this->execution_time_ms / 1000, 2).'s';
    }

    /**
     * Get formatted memory usage
     */
    public function getFormattedMemoryUsageAttribute()
    {
        if (! $this->memory_usage_mb) {
            return null;
        }

        return number_format($this->memory_usage_mb, 2).' MB';
    }

    /**
     * Get formatted peak memory usage
     */
    public function getFormattedPeakMemoryAttribute()
    {
        if (! $this->peak_memory_mb) {
            return null;
        }

        return number_format($this->peak_memory_mb, 2).' MB';
    }

    /**
     * Get short job class name
     */
    public function getShortJobClassAttribute()
    {
        return class_basename($this->job_class);
    }

    /**
     * Get truncated failure reason
     */
    public function getTruncatedFailureReasonAttribute()
    {
        if (! $this->failure_reason) {
            return null;
        }

        return strlen($this->failure_reason) > 100
            ? substr($this->failure_reason, 0, 100).'...'
            : $this->failure_reason;
    }

    /**
     * Get error type badge color
     */
    public function getErrorTypeBadgeColorAttribute()
    {
        return match ($this->error_type) {
            self::ERROR_TYPE_DATABASE => 'bg-red-100 text-red-800',
            self::ERROR_TYPE_TIMEOUT => 'bg-yellow-100 text-yellow-800',
            self::ERROR_TYPE_MEMORY => 'bg-purple-100 text-purple-800',
            self::ERROR_TYPE_NETWORK => 'bg-blue-100 text-blue-800',
            self::ERROR_TYPE_AUTHENTICATION => 'bg-orange-100 text-orange-800',
            self::ERROR_TYPE_VALIDATION => 'bg-pink-100 text-pink-800',
            self::ERROR_TYPE_EXTERNAL_API => 'bg-indigo-100 text-indigo-800',
            self::ERROR_TYPE_FILE_SYSTEM => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get error category badge color
     */
    public function getErrorCategoryBadgeColorAttribute()
    {
        return match ($this->error_category) {
            self::ERROR_CATEGORY_RECOVERABLE => 'bg-green-100 text-green-800',
            self::ERROR_CATEGORY_PERMANENT => 'bg-red-100 text-red-800',
            self::ERROR_CATEGORY_CONFIGURATION => 'bg-yellow-100 text-yellow-800',
            self::ERROR_CATEGORY_RESOURCE => 'bg-purple-100 text-purple-800',
            self::ERROR_CATEGORY_BUSINESS_LOGIC => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Determine if the failure is likely due to a database connection issue
     */
    public function isDatabaseConnectionIssue()
    {
        if ($this->error_type !== self::ERROR_TYPE_DATABASE) {
            return false;
        }

        $dbErrorPatterns = [
            'server has gone away',
            'Error while reading greeting packet',
            'Lost connection',
            'Connection refused',
            'SQLSTATE[HY000]',
            'Connection timed out',
        ];

        $failureReason = strtolower($this->failure_reason ?? '');
        $stackTrace = strtolower($this->stack_trace ?? '');

        foreach ($dbErrorPatterns as $pattern) {
            if (str_contains($failureReason, strtolower($pattern)) ||
                str_contains($stackTrace, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the failure is likely due to timeout
     */
    public function isTimeoutIssue()
    {
        if ($this->error_type === self::ERROR_TYPE_TIMEOUT) {
            return true;
        }

        $timeoutPatterns = [
            'maximum execution time',
            'timeout',
            'time limit',
            'execution time exceeded',
        ];

        $failureReason = strtolower($this->failure_reason ?? '');
        $stackTrace = strtolower($this->stack_trace ?? '');

        foreach ($timeoutPatterns as $pattern) {
            if (str_contains($failureReason, $pattern) ||
                str_contains($stackTrace, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the failure is likely due to memory issues
     */
    public function isMemoryIssue()
    {
        if ($this->error_type === self::ERROR_TYPE_MEMORY) {
            return true;
        }

        $memoryPatterns = [
            'memory limit',
            'out of memory',
            'allowed memory size',
            'memory exhausted',
        ];

        $failureReason = strtolower($this->failure_reason ?? '');
        $stackTrace = strtolower($this->stack_trace ?? '');

        foreach ($memoryPatterns as $pattern) {
            if (str_contains($failureReason, $pattern) ||
                str_contains($stackTrace, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get suggested resolution based on error type and details
     */
    public function getSuggestedResolution()
    {
        if ($this->resolution_notes) {
            return $this->resolution_notes;
        }

        if ($this->isDatabaseConnectionIssue()) {
            return 'Check database connection, consider implementing connection retry logic or increasing connection timeout.';
        }

        if ($this->isTimeoutIssue()) {
            return 'Consider increasing job timeout, breaking down large jobs into smaller chunks, or running in background.';
        }

        if ($this->isMemoryIssue()) {
            return 'Increase memory limit, optimize memory usage, or process data in smaller batches.';
        }

        return match ($this->error_type) {
            self::ERROR_TYPE_NETWORK => 'Check network connectivity and external service availability.',
            self::ERROR_TYPE_AUTHENTICATION => 'Verify credentials and authentication tokens.',
            self::ERROR_TYPE_VALIDATION => 'Review input data validation and business rules.',
            self::ERROR_TYPE_EXTERNAL_API => 'Check external API status and rate limits.',
            self::ERROR_TYPE_FILE_SYSTEM => 'Verify file permissions and disk space.',
            default => 'Review error details and stack trace for specific resolution steps.',
        };
    }

    /**
     * Create or update failed job details
     */
    public static function createOrUpdateFromFailure($failedJobUuid, $exception, $jobData, $performanceLogId = null)
    {
        $errorType = self::determineErrorType($exception);
        $errorCategory = self::determineErrorCategory($exception, $errorType);

        // Extract job information from payload
        $jobClass = $jobData['job_class'] ?? 'Unknown';
        $queue = $jobData['queue'] ?? 'default';
        $connection = $jobData['connection'] ?? 'database';
        $jobId = $jobData['job_id'] ?? null;
        $attempts = $jobData['attempts'] ?? 1;
        $maxTries = $jobData['max_tries'] ?? null;
        $timeout = $jobData['timeout'] ?? null;

        // System information
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        $workerPid = getmypid();
        $phpVersion = PHP_VERSION;

        // Server info
        $serverInfo = [
            'php_version' => $phpVersion,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => php_uname('s').' '.php_uname('r'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'timezone' => date_default_timezone_get(),
        ];

        // Context data
        $contextData = [
            'timestamp' => Carbon::now()->toISOString(),
            'environment' => app()->environment(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_id' => request()->header('X-Request-ID') ?? null,
            'job_data' => $jobData,
        ];

        $now = Carbon::now();

        return self::updateOrCreate(
            ['failed_job_uuid' => $failedJobUuid],
            [
                'job_id' => $jobId,
                'job_class' => $jobClass,
                'queue' => $queue,
                'connection' => $connection,
                'failure_reason' => $exception->getMessage(),
                'stack_trace' => $exception->getTraceAsString(),
                'payload_data' => $jobData['payload'] ?? null,
                'context_data' => $contextData,
                'memory_usage_mb' => $memoryUsage,
                'peak_memory_mb' => $peakMemory,
                'worker_pid' => $workerPid,
                'php_version' => $phpVersion,
                'server_info' => json_encode($serverInfo),
                'attempts' => $attempts,
                'max_tries' => $maxTries,
                'timeout' => $timeout,
                'first_failed_at' => $now,
                'last_failed_at' => $now,
                'error_type' => $errorType,
                'error_category' => $errorCategory,
                'is_retryable' => self::isRetryable($exception, $errorType),
                'related_job_performance_log_id' => $performanceLogId,
            ]
        );
    }

    /**
     * Determine error type from exception
     */
    private static function determineErrorType($exception)
    {
        $message = strtolower($exception->getMessage());
        $exceptionClass = get_class($exception);

        // Database errors
        if (str_contains($message, 'sqlstate') ||
            str_contains($message, 'database') ||
            str_contains($message, 'connection') ||
            str_contains($message, 'server has gone away') ||
            str_contains($exceptionClass, 'database') ||
            str_contains($exceptionClass, 'pdo')) {
            return self::ERROR_TYPE_DATABASE;
        }

        // Timeout errors
        if (str_contains($message, 'timeout') ||
            str_contains($message, 'time limit') ||
            str_contains($message, 'execution time exceeded')) {
            return self::ERROR_TYPE_TIMEOUT;
        }

        // Memory errors
        if (str_contains($message, 'memory') ||
            str_contains($message, 'out of memory') ||
            str_contains($message, 'allowed memory size')) {
            return self::ERROR_TYPE_MEMORY;
        }

        // Network errors
        if (str_contains($message, 'network') ||
            str_contains($message, 'curl') ||
            str_contains($message, 'connection refused') ||
            str_contains($message, 'host unreachable')) {
            return self::ERROR_TYPE_NETWORK;
        }

        // Authentication errors
        if (str_contains($message, 'auth') ||
            str_contains($message, 'unauthorized') ||
            str_contains($message, 'forbidden') ||
            str_contains($message, 'credentials')) {
            return self::ERROR_TYPE_AUTHENTICATION;
        }

        // Validation errors
        if (str_contains($message, 'validation') ||
            str_contains($message, 'invalid') ||
            str_contains($exceptionClass, 'validation')) {
            return self::ERROR_TYPE_VALIDATION;
        }

        // File system errors
        if (str_contains($message, 'file') ||
            str_contains($message, 'permission') ||
            str_contains($message, 'directory') ||
            str_contains($message, 'disk space')) {
            return self::ERROR_TYPE_FILE_SYSTEM;
        }

        // External API errors
        if (str_contains($message, 'api') ||
            str_contains($message, 'rate limit') ||
            str_contains($message, 'service unavailable')) {
            return self::ERROR_TYPE_EXTERNAL_API;
        }

        return self::ERROR_TYPE_EXCEPTION;
    }

    /**
     * Determine error category from exception and type
     */
    private static function determineErrorCategory($exception, $errorType)
    {
        $message = strtolower($exception->getMessage());

        // Permanent errors
        if (str_contains($message, 'not found') ||
            str_contains($message, 'does not exist') ||
            str_contains($message, 'unauthorized') ||
            str_contains($message, 'forbidden')) {
            return self::ERROR_CATEGORY_PERMANENT;
        }

        // Configuration errors
        if (str_contains($message, 'config') ||
            str_contains($message, 'setting') ||
            str_contains($message, 'environment')) {
            return self::ERROR_CATEGORY_CONFIGURATION;
        }

        // Resource errors
        if ($errorType === self::ERROR_TYPE_MEMORY ||
            $errorType === self::ERROR_TYPE_TIMEOUT ||
            str_contains($message, 'resource') ||
            str_contains($message, 'limit')) {
            return self::ERROR_CATEGORY_RESOURCE;
        }

        // Business logic errors
        if ($errorType === self::ERROR_TYPE_VALIDATION ||
            str_contains($message, 'business') ||
            str_contains($message, 'rule') ||
            str_contains($message, 'logic')) {
            return self::ERROR_CATEGORY_BUSINESS_LOGIC;
        }

        // Default to recoverable
        return self::ERROR_CATEGORY_RECOVERABLE;
    }

    /**
     * Determine if error is retryable
     */
    private static function isRetryable($exception, $errorType)
    {
        $message = strtolower($exception->getMessage());

        // Non-retryable errors
        if (str_contains($message, 'not found') ||
            str_contains($message, 'unauthorized') ||
            str_contains($message, 'forbidden') ||
            str_contains($message, 'invalid') ||
            $errorType === self::ERROR_TYPE_VALIDATION) {
            return false;
        }

        // Retryable errors
        if ($errorType === self::ERROR_TYPE_DATABASE ||
            $errorType === self::ERROR_TYPE_NETWORK ||
            $errorType === self::ERROR_TYPE_TIMEOUT ||
            $errorType === self::ERROR_TYPE_MEMORY ||
            $errorType === self::ERROR_TYPE_EXTERNAL_API) {
            return true;
        }

        return true; // Default to retryable
    }
}
