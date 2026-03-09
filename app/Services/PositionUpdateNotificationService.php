<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\PositionUpdateProgress;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Reuse the existing "position_update" UX for other long-running jobs:
 * - Broadcast PositionUpdateProgress on sequifi-{domain} (event name: position-update)
 * - Persist to Redis via NotificationService with type=position_update
 *
 * This is best-effort only and must never block job execution.
 */
class PositionUpdateNotificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @param int|null $primaryUserId If set, store notification for this user; otherwise store for default recipients.
     * @param string $title Displayed as positionName in the existing UX (e.g., "Sales import", "Payroll retry").
     * @param string $status processing|completed|failed (position_update UX uses these)
     * @param int $progress 0..100
     * @param string $message Human-readable status
     * @param string $uniqueKey Stable key for this run (used for updates/dismiss)
     * @param string|null $initiatedAt ISO string or datetime string
     * @param string|null $completedAt ISO string or datetime string
     * @param int|null $updatedById The user who initiated the job (used as updatedById in the payload)
     * @param string|null $updatedByName Optional override for initiator name
     * @param array<int>|null $recipientUserIds Explicit recipients override
     * @param int $positionId Optional numeric ID (0 for non-position jobs)
     */
    public function notify(
        ?int $primaryUserId,
        string $title,
        string $status,
        int $progress,
        string $message,
        string $uniqueKey,
        ?string $initiatedAt = null,
        ?string $completedAt = null,
        ?int $updatedById = null,
        ?string $updatedByName = null,
        ?array $recipientUserIds = null,
        int $positionId = 0,
    ): void {
        $recipients = $recipientUserIds
            ?? ($primaryUserId ? [$primaryUserId] : $this->getDefaultRecipients());
        $recipients = array_values(array_unique(array_filter($recipients, fn ($id) => is_int($id) && $id > 0)));
        if ($recipients === []) {
            return;
        }

        $status = $this->normalizeStatus($status);
        $progress = max(0, min(100, $progress));
        $message = $this->normalizeUserMessage($message);
        if ($message === '' || $uniqueKey === '') {
            return;
        }

        $initiatedAt = $initiatedAt ?: now()->toDateTimeString();
        if (in_array($status, ['completed', 'failed'], true)) {
            $completedAt = $completedAt ?: now()->toDateTimeString();
        }

        $initiatorId = ($updatedById !== null && $updatedById > 0) ? $updatedById : 0;
        $initiatorName = $updatedByName
            ?: ($initiatorId > 0 ? $this->resolveUserName($initiatorId) : 'System');

        $payload = [
            // Keep required PositionUpdateProgress fields
            'positionId' => $positionId,
            'positionName' => $title,
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'updatedBy' => $initiatorName,
            'updatedById' => $initiatorId,
            'initiatedAt' => $initiatedAt,
            'completedAt' => $completedAt,
            'uniqueKey' => $uniqueKey,
            'timestamp' => now()->toISOString(),
        ];

        // 1) Broadcast ONCE (best-effort). This is a public channel; clients filter as needed.
        try {
            broadcast(new PositionUpdateProgress($payload));
        } catch (\Throwable $e) {
            Log::debug('[PositionUpdateNotificationService] Broadcast failed', [
                'unique_key' => $uniqueKey,
                'error' => $e->getMessage(),
            ]);
        }

        // 2) Persist to Redis for each recipient (best-effort)
        foreach ($recipients as $userId) {
            try {
                $this->notificationService->storeNotification($userId, 'position_update', $payload);
            } catch (\Throwable $e) {
                Log::debug('[PositionUpdateNotificationService] Redis store failed', [
                    'user_id' => $userId,
                    'unique_key' => $uniqueKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Prevent huge/technical messages (stack traces, multi-line errors) from leaking into UI.
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
            Log::debug('[PositionUpdateNotificationService] Failed to resolve default recipients', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function resolveUserName(int $userId): string
    {
        try {
            return (string) (User::query()->whereKey($userId)->value('name') ?: 'System');
        } catch (\Throwable) {
            return 'System';
        }
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'started', 'processing', 'running' => 'processing',
            'completed' => 'completed',
            'failed' => 'failed',
            default => 'processing',
        };
    }
}


