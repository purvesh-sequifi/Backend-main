<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExcelImportHistory;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * NotificationService - Generic Redis-based notification persistence
 * 
 * Stores ANY type of notification in Redis with 3-day TTL
 * Supports: position updates, payroll, sales exports, reports, etc.
 * User-specific keys prevent cross-user data leakage
 * 
 * @package App\Services
 */
class NotificationService
{
    /** @var int 3 days in seconds - Users can dismiss manually, so longer retention is better */
    const TTL = 259200;  // 3 days (72 hours)
    
    /** @var string Redis key prefix - generic for all notification types */
    const PREFIX = 'notifications';

    /**
     * Excel import progress cache TTL (seconds).
     * Kept short so Notification progress bar matches Import History (same display_progress).
     */
    private const EXCEL_IMPORT_PROGRESS_CACHE_TTL = 2;

    private function excelImportProgressCacheKey(int $excelId): string
    {
        return 'notif:excel_import_progress:' . $excelId;
    }

    /**
     * Get ExcelImportHistory progress snapshots with a short Redis cache.
     *
     * @param array<int,int> $excelIds
     * @return array<int, array{processed:int,total:int,pct:float}>
     */
    private function getExcelImportProgressSnapshots(array $excelIds): array
    {
        $excelIds = array_values(array_unique(array_map(static fn ($id) => (int) $id, $excelIds)));
        $excelIds = array_values(array_filter($excelIds, static fn ($id) => $id > 0));
        if ($excelIds === []) {
            return [];
        }

        $snapshots = [];
        $missing = [];

        foreach ($excelIds as $excelId) {
            $cached = Redis::get($this->excelImportProgressCacheKey($excelId));
            if (! is_string($cached) || $cached === '') {
                $missing[] = $excelId;
                continue;
            }

            $decoded = json_decode($cached, true);
            if (! is_array($decoded)) {
                $missing[] = $excelId;
                continue;
            }

            $processed = (int) ($decoded['processed'] ?? 0);
            $total = (int) ($decoded['total'] ?? 0);
            $pct = (float) ($decoded['pct'] ?? 0.0);
            $snapshots[$excelId] = [
                'processed' => $processed,
                'total' => $total,
                'pct' => $pct,
            ];
        }

        if ($missing !== []) {
            $rows = ExcelImportHistory::query()
                ->whereIn('id', $missing)
                ->get([
                    'id',
                    'new_records',
                    'updated_records',
                    'error_records',
                    'total_records',
                    'status',
                    'current_phase',
                    'phase_progress',
                ]);

            foreach ($rows as $row) {
                $excelId = (int) ($row->id ?? 0);
                if ($excelId <= 0) {
                    continue;
                }

                $total = (int) ($row->total_records ?? 0);
                $processed = (int) ($row->new_records ?? 0)
                    + (int) ($row->updated_records ?? 0)
                    + (int) ($row->error_records ?? 0);
                $rowPct = $total > 0 ? (float) (($processed / $total) * 100) : 0.0;

                // Use same display_progress logic as Import History API so both UIs show same %.
                $currentPhase = $row->current_phase ?? null;
                $phaseProgress = isset($row->phase_progress) ? (float) $row->phase_progress : null;
                $status = (int) ($row->status ?? 1);

                // Use same display_progress logic as Import History API so both UIs show the same %.
                $pct = ExcelImportHistory::resolveDisplayProgress(
                    status: $status,
                    currentPhase: $currentPhase,
                    phaseProgress: $phaseProgress,
                    rowPct: $rowPct,
                    saleProcessingFallbackToRowPct: true,
                );

                $payload = [
                    'processed' => $processed,
                    'total' => $total,
                    'pct' => $pct,
                ];

                try {
                    Redis::setex(
                        $this->excelImportProgressCacheKey($excelId),
                        self::EXCEL_IMPORT_PROGRESS_CACHE_TTL,
                        json_encode($payload)
                    );
                } catch (\Throwable) {
                    // best-effort only
                }

                $snapshots[$excelId] = $payload;
            }
        }

        return $snapshots;
    }
    
    /**
     * Store notification in Redis with 3-day TTL
     * 
     * @param int $userId User ID for isolation
     * @param string $type Notification type (position_update, payroll, sales_export, etc.)
     * @param array $data Notification data (must include 'uniqueKey')
     * @return bool Success status
     */
    public function storeNotification(int $userId, string $type, array $data): bool
    {
        try {
            // Validate required field
            if (empty($data['uniqueKey'])) {
                Log::warning('[NotificationService] Missing uniqueKey in notification data', [
                    'user_id' => $userId,
                    'type' => $type,
                    'data_keys' => array_keys($data)
                ]);
                return false;
            }
            
            $key = $this->buildKey($userId, $type, $data['uniqueKey']);

            // Idempotency guard (sales imports):
            // In rare cases the same "started" signal can be emitted twice for the same import uniqueKey
            // (e.g., client retry/double-submit). That creates duplicate "Sales import started" toasts.
            // We only dedupe this one case to avoid impacting other notification flows.
            if (
                $type === 'sales_excel_import'
                && ($data['status'] ?? null) === 'started'
                && $this->notificationExists($userId, $type, (string) $data['uniqueKey'])
            ) {
                Log::debug('[NotificationService] Skipped duplicate sales_excel_import started notification', [
                    'user_id' => $userId,
                    'type' => $type,
                    'unique_key' => $data['uniqueKey'],
                ]);
                return true;
            }
            
            // Add timestamp if not present
            if (!isset($data['timestamp'])) {
                $data['timestamp'] = now()->toISOString();
            }
            
            // Prevent huge/technical messages (stack traces, multi-line errors) from leaking into UI.
            if (isset($data['message']) && is_string($data['message'])) {
                $data['message'] = $this->normalizeUserMessage($data['message']);
            }
            
            // Store in Redis with 2-hour expiration
            $stored = Redis::setex($key, self::TTL, json_encode($data));
            
            if ($stored) {
                Log::debug('[NotificationService] Stored in Redis', [
                    'user_id' => $userId,
                    'type' => $type,
                    'unique_key' => $data['uniqueKey'],
                    'status' => $data['status'] ?? 'unknown',
                    'progress' => $data['progress'] ?? 0,
                    'ttl_seconds' => self::TTL
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            // CRITICAL: Never throw - notification storage failure shouldn't break jobs
            Log::error('[NotificationService] Failed to store notification', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'user_id' => $userId,
                'unique_key' => $data['uniqueKey'] ?? 'unknown'
            ]);
            return false;
        }
    }

    public function notificationExists(int $userId, string $type, string $uniqueKey): bool
    {
        try {
            $key = $this->buildKey($userId, $type, $uniqueKey);
            $exists = Redis::exists($key);
            return (bool) (is_int($exists) ? ($exists > 0) : $exists);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Keep messages user-friendly and safe for UI rendering:
     * - collapse whitespace/newlines
     * - hard-limit length
     */
    private function normalizeUserMessage(string $message, int $maxLen = 220): string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $message));
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) <= $maxLen) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, max(0, $maxLen - 1))) . '…';
    }
    
    /**
     * Get all active notifications for a user
     * 
     * @param int $userId User ID
     * @param string|null $type Optional filter by type (position_update, payroll, etc.)
     * @return array Array of notification data
     */
    public function getActiveNotifications(int $userId, ?string $type = null): array
    {
        try {
            // Laravel's Redis facade automatically adds the configured prefix
            // So we DON'T add it manually - just use the base pattern
            $pattern = $this->buildPattern($userId, $type);

            // IMPORTANT:
            // Do NOT use Redis KEYS in production paths. With large datasets it can block Redis and/or time out.
            // Use SCAN with a hard cap instead, then sort the sampled notifications by timestamp.
            $keys = $this->scanKeys($pattern, 5000);
            
            if (empty($keys)) {
                Log::debug('[NotificationService] No active notifications found', [
                    'user_id' => $userId,
                    'pattern' => $pattern
                ]);
                return [];
            }
            
            $notifications = [];
            $prefix = (string) (config('database.redis.options.prefix', '') ?? '');
            foreach ($keys as $key) {
                // keys() returns full key with prefix: new_database_position_notifications:...
                // get() also expects key WITHOUT manually added prefix (it adds automatically)
                // So we need to strip the runtime prefix from the key
                $keyStr = is_string($key) ? $key : (string) $key;
                $keyWithoutPrefix = $prefix !== '' && str_starts_with($keyStr, $prefix)
                    ? substr($keyStr, strlen($prefix))
                    : $keyStr;
                $data = Redis::get($keyWithoutPrefix);
                if ($data) {
                    $decoded = json_decode($data, true);
                    if ($decoded && is_array($decoded)) {
                        $notifications[] = $decoded;
                    }
                }
            }
            
            // Sort by timestamp (newest first)
            usort($notifications, function($a, $b) {
                $timeA = strtotime($a['timestamp'] ?? $a['initiatedAt'] ?? '1970-01-01');
                $timeB = strtotime($b['timestamp'] ?? $b['initiatedAt'] ?? '1970-01-01');
                return $timeB - $timeA;
            });

            // Defensive cap: don't return massive arrays to the frontend.
            // The UI only needs the most recent notifications.
            $notifications = array_slice($notifications, 0, 200);

            // Keep Sales import notification progress aligned with Import History progress.
            // Import History uses: ((new_records + updated_records + error_records) / total_records) * 100.
            // The notification is emitted on a cadence (chunks), so it can lag behind the DB counters.
            // We "refresh" the percentage on read so the two UIs match.
            if ($type === null || $type === 'sales_excel_import') {
                $excelIds = [];
                foreach ($notifications as $n) {
                    if (($n['type'] ?? null) !== 'sales_excel_import') {
                        continue;
                    }
                    // We want Import History and Sales import notifications to match while the import is active.
                    // Treat started/queued/processing as active states.
                    if (! in_array(($n['status'] ?? null), ['queued', 'started', 'processing'], true)) {
                        continue;
                    }
                    $excelId = $n['meta']['excel_id'] ?? null;
                    if (is_int($excelId) && $excelId > 0) {
                        $excelIds[$excelId] = true;
                        continue;
                    }
                    if (is_numeric($excelId) && (int) $excelId > 0) {
                        $excelIds[(int) $excelId] = true;
                    }
                }

                if ($excelIds !== []) {
                    $snapshots = $this->getExcelImportProgressSnapshots(array_keys($excelIds));
                    $enriched = 0;
                    foreach ($notifications as &$n) {
                        if (($n['type'] ?? null) !== 'sales_excel_import') {
                            continue;
                        }
                        if (! in_array(($n['status'] ?? null), ['queued', 'started', 'processing'], true)) {
                            continue;
                        }
                        $excelId = $n['meta']['excel_id'] ?? null;
                        $excelId = is_numeric($excelId) ? (int) $excelId : 0;
                        if ($excelId <= 0 || ! isset($snapshots[$excelId])) {
                            continue;
                        }

                        $processed = (int) ($snapshots[$excelId]['processed'] ?? 0);
                        $total = (int) ($snapshots[$excelId]['total'] ?? 0);
                        $pct = (float) ($snapshots[$excelId]['pct'] ?? 0.0);

                        // Same integer percentage as Import History (e.g. 25).
                        $n['progress'] = (int) max(0, min(100, (int) round($pct)));
                        $n['message'] = "Importing sales rows ({$processed} / {$total})...";
                        $n['meta'] = is_array($n['meta'] ?? null) ? $n['meta'] : [];
                        $n['meta']['processed'] = $processed;
                        $n['meta']['total'] = $total;
                        $n['meta']['progress_percentage'] = $pct;
                        $enriched++;
                    }
                    unset($n);
                }
            }

            // Also align the *position_update* "Sales import" card, since the UI may render that card
            // (emitted via PositionUpdateNotificationService) instead of the native `sales_excel_import` card.
            // That card stores integer progress, so it can drift from Import History unless we refresh it here.
            if ($type === null || $type === 'position_update') {
                $excelIds = [];

                foreach ($notifications as $n) {
                    if (($n['type'] ?? null) !== 'position_update') {
                        continue;
                    }

                    // We only care about the Sales import card
                    if (($n['positionName'] ?? null) !== 'Sales import') {
                        continue;
                    }

                    if (! in_array(($n['status'] ?? null), ['started', 'processing'], true)) {
                        continue;
                    }

                    $uniqueKey = (string) ($n['uniqueKey'] ?? '');
                    if ($uniqueKey === '') {
                        continue;
                    }

                    // Supported formats:
                    // - excel_sales_{excelId}_{timestamp}
                    // - sales_excel_import_{excelId}
                    if (preg_match('/^excel_sales_(\\d+)_/', $uniqueKey, $m) === 1) {
                        $excelIds[(int) $m[1]] = true;
                        continue;
                    }
                    if (preg_match('/^sales_excel_import_(\\d+)$/', $uniqueKey, $m) === 1) {
                        $excelIds[(int) $m[1]] = true;
                        continue;
                    }
                }

                if ($excelIds !== []) {
                    $snapshots = $this->getExcelImportProgressSnapshots(array_keys($excelIds));

                    foreach ($notifications as &$n) {
                        if (($n['type'] ?? null) !== 'position_update' || ($n['positionName'] ?? null) !== 'Sales import') {
                            continue;
                        }
                        if (! in_array(($n['status'] ?? null), ['started', 'processing'], true)) {
                            continue;
                        }

                        $uniqueKey = (string) ($n['uniqueKey'] ?? '');
                        $excelId = 0;
                        if (preg_match('/^excel_sales_(\\d+)_/', $uniqueKey, $m) === 1) {
                            $excelId = (int) $m[1];
                        } elseif (preg_match('/^sales_excel_import_(\\d+)$/', $uniqueKey, $m) === 1) {
                            $excelId = (int) $m[1];
                        }
                        if ($excelId <= 0 || ! isset($snapshots[$excelId])) {
                            continue;
                        }

                        $processed = (int) ($snapshots[$excelId]['processed'] ?? 0);
                        $total = (int) ($snapshots[$excelId]['total'] ?? 0);
                        $pct = (float) ($snapshots[$excelId]['pct'] ?? 0.0);

                        // Same integer percentage as Import History (e.g. 25).
                        $n['progress'] = (int) max(0, min(100, (int) round($pct)));
                        $n['message'] = "Importing sales rows ({$processed} / {$total})...";
                    }
                    unset($n);
                }
            }
            
            Log::debug('[NotificationService] Retrieved from Redis', [
                'user_id' => $userId,
                'count' => count($notifications),
                'keys_found' => count($keys)
            ]);
            
            return $notifications;
            
        } catch (Exception $e) {
            Log::error('[NotificationService] Failed to get notifications', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'user_id' => $userId
            ]);
            // Return empty array on error (graceful degradation)
            return [];
        }
    }

    /**
     * Scan Redis keys with a hard cap (safer alternative to KEYS).
     *
     * @param string $pattern
     * @param int $maxKeys
     * @return array<int, string>
     */
    private function scanKeys(string $pattern, int $maxKeys = 5000): array
    {
        $maxKeys = max(1, $maxKeys);

        try {
            // NOTE:
            // Laravel/PhpRedis applies `database.redis.options.prefix` automatically for most commands (GET/SET/KEYS),
            // but SCAN does NOT consistently apply it in this codebase/environment.
            // So we must prefix the MATCH pattern ourselves to actually find keys.
            $prefix = (string) (config('database.redis.options.prefix', '') ?? '');
            $scanPattern = ($prefix !== '' && !str_starts_with($pattern, $prefix))
                ? ($prefix . $pattern)
                : $pattern;

            $cursor = '0';
            $keys = [];

            // phpredis returns: array{0:string,1:array<int,string>}
            // predis may return: array{0:int,1:array<int,string>}
            do {
                $result = Redis::scan($cursor, 'MATCH', $scanPattern, 'COUNT', 1000);
                if (!is_array($result) || count($result) < 2) {
                    break;
                }

                $cursor = (string) ($result[0] ?? '0');
                $batch = $result[1] ?? [];

                if (is_array($batch)) {
                    foreach ($batch as $k) {
                        $keys[] = (string) $k;
                        if (count($keys) >= $maxKeys) {
                            return $keys;
                        }
                    }
                }
            } while ($cursor !== '0');

            // If SCAN yields nothing but keys exist, fall back to KEYS (best-effort).
            // This protects against Redis client/cluster-mode incompatibilities where SCAN silently returns 0 keys.
            if ($keys === []) {
                try {
                    $fallback = Redis::keys($pattern);
                    if (is_array($fallback) && $fallback !== []) {
                        Log::warning('[NotificationService] Redis SCAN returned 0 keys; falling back to KEYS', [
                            'pattern' => $pattern,
                            'scan_pattern' => $scanPattern,
                            'fallback_keys_found' => count($fallback),
                        ]);
                        return array_slice(array_map(static fn ($k) => (string) $k, $fallback), 0, $maxKeys);
                    }
                } catch (\Throwable) {
                    // ignore - return empty keys below
                }
            }

            return $keys;
        } catch (\Throwable $e) {
            // Fallback to KEYS (best-effort) if SCAN isn't supported by the client.
            Log::warning('[NotificationService] Redis SCAN failed; falling back to KEYS', [
                'pattern' => $pattern,
                'error_class' => get_class($e),
                'error' => $e->getMessage(),
            ]);
            try {
                $fallback = Redis::keys($pattern);
                if (!is_array($fallback)) {
                    return [];
                }
                return array_slice(array_map(static fn ($k) => (string) $k, $fallback), 0, $maxKeys);
            } catch (\Throwable) {
                return [];
            }
        }
    }
    
    /**
     * Dismiss (delete) a notification from Redis
     * 
     * @param int $userId User ID
     * @param string $type Notification type
     * @param string $uniqueKey Notification unique key
     * @return bool True if deleted, false otherwise
     */
    public function dismissNotification(int $userId, string $type, string $uniqueKey): bool
    {
        try {
            $key = $this->buildKey($userId, $type, $uniqueKey);
            $deleted = Redis::del($key);
            
            Log::info('[NotificationService] Notification dismissed', [
                'user_id' => $userId,
                'unique_key' => $uniqueKey,
                'deleted' => (bool)$deleted
            ]);
            
            return (bool)$deleted;
            
        } catch (Exception $e) {
            Log::error('[NotificationService] Failed to dismiss notification', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'user_id' => $userId,
                'unique_key' => $uniqueKey
            ]);
            return false;
        }
    }
    
    /**
     * Build Redis key for user-specific notification
     * Format: notifications:{user_id}:{type}:{unique_key}
     * 
     * @param int $userId
     * @param string $type Notification type (position_update, payroll, etc.)
     * @param string $uniqueKey
     * @return string
     */
    private function buildKey(int $userId, string $type, string $uniqueKey): string
    {
        return sprintf('%s:%d:%s:%s', self::PREFIX, $userId, $type, $uniqueKey);
    }
    
    /**
     * Build Redis pattern for user's notifications
     * Format: notifications:{user_id}:* (all types)
     * Format: notifications:{user_id}:{type}:* (specific type)
     * 
     * @param int $userId
     * @param string|null $type Optional type filter
     * @return string
     */
    private function buildPattern(int $userId, ?string $type = null): string
    {
        if ($type) {
            return sprintf('%s:%d:%s:*', self::PREFIX, $userId, $type);
        }
        return sprintf('%s:%d:*', self::PREFIX, $userId);
    }
    
    /**
     * Get TTL in seconds
     * 
     * @return int
     */
    public function getTTL(): int
    {
        return self::TTL;
    }
}

