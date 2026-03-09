<?php

namespace App\Services;

use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRawDataHistoryLog;
use App\Models\SaleMasterExcluded;
use App\Models\User;

class LegacyLogsQueryService
{
    /**
     * Query legacy tables for records related to a user by emails and user id.
     * Returns lightweight stats to avoid memory pressure.
     */
    public function queryForUser(User $user, array $emails = []): array
    {
        $emails = array_values(array_filter(array_unique(array_map('strtolower', $emails))));
        $userId = $user->id;

        $response = [
            'user_id' => $userId,
            'emails' => $emails,
            'counts' => [
                'legacy_histories' => 0,
                'legacy_histories_log' => 0,
                'sale_masters_excluded' => 0,
            ],
            'sample_ids' => [
                'legacy_histories' => [],
                'legacy_histories_log' => [],
                'sale_masters_excluded' => [],
            ],
        ];

        $maxIds = (int) config('legacy_logs_sync.max_sample_ids', 50);

        // legacy_api_raw_data_histories
        $historiesQuery = LegacyApiRawDataHistory::query()
            ->where(function ($q) use ($emails, $userId) {
                if (! empty($emails)) {
                    $q->whereIn('sales_rep_email', $emails);
                }
                $q->orWhere('closer1_id', $userId)
                    ->orWhere('setter1_id', $userId)
                    ->orWhere('closer2_id', $userId)
                    ->orWhere('setter2_id', $userId);
            });

        $response['counts']['legacy_histories'] = (clone $historiesQuery)->count();
        $response['sample_ids']['legacy_histories'] = (clone $historiesQuery)
            ->select('id')
            ->orderByDesc('id')
            ->limit($maxIds)
            ->pluck('id')
            ->all();

        // legacy_api_raw_data_histories_log
        $logsQuery = LegacyApiRawDataHistoryLog::query()
            ->where(function ($q) use ($emails, $userId) {
                if (! empty($emails)) {
                    $q->whereIn('sales_rep_email', $emails);
                }
                $q->orWhere('closer1_id', $userId)
                    ->orWhere('setter1_id', $userId)
                    ->orWhere('closer2_id', $userId)
                    ->orWhere('setter2_id', $userId);
            });

        $response['counts']['legacy_histories_log'] = (clone $logsQuery)->count();
        $response['sample_ids']['legacy_histories_log'] = (clone $logsQuery)
            ->select('id')
            ->orderByDesc('id')
            ->limit($maxIds)
            ->pluck('id')
            ->all();

        // sale_masters_excluded
        $ufciQuery = SaleMasterExcluded::query()
            ->where(function ($q) use ($emails, $userId) {
                $q->where('user_id', $userId)
                    ->orWhere('closer1_id', $userId)
                    ->orWhere('setter1_id', $userId)
                    ->orWhere('closer2_id', $userId)
                    ->orWhere('setter2_id', $userId);
                if (! empty($emails)) {
                    $q->orWhereIn('sales_rep_email', $emails);
                }
            });

        $response['counts']['sale_masters_excluded'] = (clone $ufciQuery)->count();
        $response['sample_ids']['sale_masters_excluded'] = (clone $ufciQuery)
            ->select('id')
            ->orderByDesc('id')
            ->limit($maxIds)
            ->pluck('id')
            ->all();

        return $response;
    }

    /**
     * Convenience method to build a default email set for a user.
     */
    public function collectUserEmails(User $user, array $extra = []): array
    {
        $emails = [
            strtolower((string) $user->email),
            strtolower((string) $user->work_email),
        ];
        foreach ($extra as $e) {
            if (is_string($e) && $e !== '') {
                $emails[] = strtolower($e);
            }
        }

        return array_values(array_filter(array_unique($emails)));
    }
}
