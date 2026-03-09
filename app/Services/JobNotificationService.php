<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\JobUpdateProgress;
use App\Services\PositionUpdateNotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * JobNotificationService
 *
 * Small wrapper that mirrors the "position update" notification pattern:
 * - Broadcast progress updates on sequifi-{domain} (best-effort)
 * - Persist per-user notification snapshots in Redis (NotificationService)
 *
 * Note: Redis persistence is user-scoped. To support the admin panel requirement
 * ("all super admins can see any background process"), we always include active
 * super admins as recipients (plus the initiator, if any).
 */
class JobNotificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly PositionUpdateNotificationService $positionUpdateNotificationService,
    ) {}

    /**
     * @param  int|null $recipientUserId Primary recipient (initiator). If null, recipients default to super admins.
     * @param  string   $type            Notification type (e.g. sales_excel_import, sales_recalc, payroll_retry)
     * @param  string   $job             Friendly job name (e.g. SaleMasterJob)
     * @param  string   $status          started|processing|completed|failed
     * @param  float    $progress        0-100 (can be decimal; Import History uses 2dp)
     * @param  string   $message         Human-friendly message
     * @param  string   $uniqueKey       Stable key per run (used to update/overwrite the same Redis entry)
     * @param  string|null $initiatedAt
     * @param  string|null $completedAt
     * @param  array    $meta            Extra info for UI (counts, ids, batch_id, etc.)
     * @param  array<int>|null $recipientUserIds Optional explicit recipients list (overrides recipientUserId/defaults)
     */
    public function notify(
        ?int $recipientUserId,
        string $type,
        string $job,
        string $status,
        float $progress,
        string $message,
        string $uniqueKey,
        ?string $initiatedAt = null,
        ?string $completedAt = null,
        array $meta = [],
        ?array $recipientUserIds = null,
    ): void {
        // Idempotency guard (sales imports):
        // If the same request is retried/double-submitted, we can receive the same "started" event twice
        // for the same uniqueKey. That yields duplicate "Sales import started" toasts.
        // We only guard this one case and DO NOT alter other job types (sales_process / sales_recalculate).
        if ($type === 'sales_excel_import' && $status === 'started' && $recipientUserId !== null) {
            if ($this->notificationService->notificationExists($recipientUserId, $type, $uniqueKey)) {
                return;
            }
        }

        $adminRecipients = $this->getDefaultRecipients();

        // NOTE:
        // Historically we avoided notifying "open sales/tiered recalculation" when there was no initiator context
        // to prevent notification spam. However, admin panel users must be able to see ALL background processes.
        // So in the "no initiator" case we still notify admins (and only admins).
        if (
            $recipientUserId === null
            && $recipientUserIds === null
            && in_array($type, ['sales_recalculate_open_sales', 'sales_recalculate_open_tiered'], true)
        ) {
            $recipients = $adminRecipients;
        } else {
            $recipients = $recipientUserIds ?? ($recipientUserId ? [$recipientUserId] : []);
            $recipients = array_merge($recipients, $adminRecipients);
        }

        $recipients = array_values(array_unique(array_filter($recipients, fn ($id) => is_int($id) && $id > 0)));

        $progressFloat = (float) max(0, min(100, $progress));
        // Broadcast event uses int progress (legacy), but Redis payload can keep decimals for UI accuracy.
        $progressInt = (int) floor($progressFloat);
        $message = $this->normalizeUserMessage($message);

        $payload = [
            'type' => $type,
            'job' => $job,
            'status' => $status,
            'progress' => $progressFloat,
            'message' => $message,
            'recipientUserIds' => $recipients,
            'initiatedAt' => $initiatedAt,
            'completedAt' => $completedAt,
            'uniqueKey' => $uniqueKey,
            'meta' => $meta,
            'timestamp' => now()->toISOString(),
        ];

        // 0) Optionally emit the "position_update" UX bridge (public channel + Redis + /v2/notifications).
        // Some workflows prefer the native job notification cards only.
        //
        // CSV pipeline: avoid showing Sales calculation/recalculation as a "Position Update" card.
        // The UI can still show the native `sales_recalculate` card with the correct title/message.
        // Note: Frontend may show "Position Update" as sub-header for position_update type notifications.
        // This is a frontend limitation - backend cannot change the sub-header label.
        $emitPositionUpdateUx = $this->shouldEmitPositionUpdateUx($type);
        if (
            $emitPositionUpdateUx
            && in_array($type, ['sales_recalculate', 'sales_calculation'], true)
            && (
                array_key_exists('excel_id', $meta)
                || str_starts_with($uniqueKey, 'sales_recalculate_excel_')
                || str_starts_with($uniqueKey, 'sales_calculation_excel_')
            )
        ) {
            $emitPositionUpdateUx = false;
        }

        if ($emitPositionUpdateUx) {
            try {
                $this->positionUpdateNotificationService->notify(
                    primaryUserId: $recipientUserId,
                    title: $this->resolvePositionUpdateTitle($type, $job),
                    status: $status,
                    progress: $progressInt,
                    message: $message,
                    uniqueKey: $uniqueKey,
                    initiatedAt: $initiatedAt,
                    completedAt: $completedAt,
                    updatedById: $recipientUserId,
                    updatedByName: null,
                    recipientUserIds: $recipients,
                    positionId: 0,
                );
            } catch (\Throwable $e) {
                Log::debug('[JobNotificationService] position_update emit failed', [
                    'type' => $type,
                    'job' => $job,
                    'unique_key' => $uniqueKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 1) Broadcast (best-effort)
        try {
            $broadcastPayload = $payload;
            $broadcastPayload['progress'] = $progressInt;
            broadcast(new JobUpdateProgress($broadcastPayload));
        } catch (\Throwable $e) {
            Log::debug('[JobNotificationService] Broadcast failed', [
                'type' => $type,
                'job' => $job,
                'unique_key' => $uniqueKey,
                'error' => $e->getMessage(),
            ]);
        }

        // 2) Persist per-user in Redis (best-effort)
        foreach ($recipients as $userId) {
            try {
                $this->notificationService->storeNotification($userId, $type, $payload);
            } catch (\Throwable $e) {
                Log::debug('[JobNotificationService] Redis store failed', [
                    'type' => $type,
                    'job' => $job,
                    'user_id' => $userId,
                    'unique_key' => $uniqueKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Ensure job notifications remain user-friendly:
     * - collapse whitespace/newlines (stack traces can be huge)
     * - hard-limit size for UI safety
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

    private function resolvePositionUpdateTitle(string $type, string $job): string
    {
        // Special-case: CSV brand-new imports intentionally use "Sales calculation" wording,
        // but the notification type remains `sales_recalculate` for backward compatibility.
        // When the caller passes a more accurate label in `$job`, prefer it for the UI title.
        if ($type === 'sales_recalculate') {
            $normalized = strtolower(trim($job));
            if ($normalized === 'sales calculation' || str_contains($normalized, 'sales calculation')) {
                return $job;
            }
        }

        // Use friendly UX titles (reusing the position_update notification card UI).
        return match ($type) {
            'sales_excel_import' => 'Sales import',
            'sales_master', 'sales_master_lambda', 'sales_process', 'fieldroutes_backdate_sales' => 'Sales processing',
            'sales_recalculate', 'sales_recalculate_open_sales', 'sales_recalculate_open_tiered' => 'Sales recalculation',
            'payroll_failed_records_reprocess' => 'Payroll retry',
            default => $job,
        };
    }

    private function shouldEmitPositionUpdateUx(string $type): bool
    {
        // Avoid duplicate UX cards: some flows should show only their native job notifications.
        return ! in_array($type, ['sales_excel_import', 'sales_process', 'sales_master', 'sales_master_lambda', 'employment_package_update'], true);
    }

    /**
     * Default recipients for system jobs with no explicit initiator.
     *
     * @return array<int>
     */
    private function getDefaultRecipients(): array
    {
        try {
            return User::query()
                ->where('is_super_admin', 1)
                ->where('dismiss', 0)
                ->where('terminate', 0)
                ->where('contract_ended', 0)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        } catch (\Throwable $e) {
            Log::debug('[JobNotificationService] Failed to resolve default recipients', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}



