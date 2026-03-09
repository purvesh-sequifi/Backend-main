<?php

namespace App\Http\Services;

use App\Models\OnboardingEmployees;
use App\Models\User;
use App\Models\UserCommissionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserRedlines;
use App\Models\UserUpfrontHistory;
use App\Models\UserWithheldHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardHistoryService
{
    /**
     * Get all history data for a user in a single optimized call with caching
     */
    public function getUserHistoryData(User $user): array
    {
        $userId = $user->id;
        $isSuperAdmin = $user->is_super_admin == 1;
        $isManager = $user->is_manager == 1;

        // Cache key based on user role and ID
        $cacheKey = $this->getCacheKey($userId, $isSuperAdmin, $isManager);

        // Cache for 10 minutes to balance performance and data freshness
        return Cache::remember($cacheKey, 600, function () use ($userId, $isSuperAdmin, $isManager) {
            // For super admin - get all records without user_id filter
            if ($isSuperAdmin) {
                return $this->getSuperAdminHistoryData($userId);
            }

            // For managers and regular users - filter by user_id
            return $this->getUserSpecificHistoryData($userId, $isManager);
        });
    }

    /**
     * Generate cache key for dashboard history data
     */
    private function getCacheKey(int $userId, bool $isSuperAdmin, bool $isManager): string
    {
        $role = $isSuperAdmin ? 'superadmin' : ($isManager ? 'manager' : 'user');

        return "dashboard_history_{$role}_{$userId}";
    }

    /**
     * Get history data for super admin (no user_id filter)
     */
    private function getSuperAdminHistoryData(int $userId): array
    {
        return [
            'override' => $this->getOverrideHistorySuperAdmin(),
            'redline' => $this->getRedlineHistorySuperAdmin(),
            'commission' => UserCommissionHistory::select('id', 'user_id', 'old_commission', 'self_gen_user')
                ->where('old_commission', '!=', 0)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
            'upfront' => UserUpfrontHistory::select('id', 'user_id', 'old_upfront_pay_amount')
                ->where('old_upfront_pay_amount', '!=', 0)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
            'withheld' => UserWithheldHistory::select('id', 'user_id', 'old_withheld_amount')
                ->where('old_withheld_amount', '!=', 0)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
            'user_organization' => UserOrganizationHistory::select('id', 'user_id')
                ->where('user_id', $userId)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
            'hiring' => OnboardingEmployees::select('id', 'hired_by_uid', 'status_id')
                ->where('hired_by_uid', $userId)
                ->where('status_id', 1)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
        ];
    }

    /**
     * Get history data for specific user (managers and regular users)
     */
    private function getUserSpecificHistoryData(int $userId, bool $isManager): array
    {
        return [
            'override' => UserOverrideHistory::select('id', 'user_id')
                ->where('user_id', $userId)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
            'redline' => UserRedlines::select('id', 'user_id', 'redline_amount_type')
                ->where('user_id', $userId)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
            'commission' => UserCommissionHistory::select('id', 'user_id', 'old_commission', 'self_gen_user')
                ->where('user_id', $userId)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
            'upfront' => UserUpfrontHistory::select('id', 'user_id', 'old_upfront_pay_amount')
                ->where('user_id', $userId)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
            'withheld' => UserWithheldHistory::select('id', 'user_id', 'old_withheld_amount')
                ->where('user_id', $userId)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
            'user_organization' => UserOrganizationHistory::select('id', 'user_id')
                ->where('user_id', $userId)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
            'hiring' => OnboardingEmployees::select('id', 'hired_by_uid', 'status_id')
                ->where('hired_by_uid', $userId)
                ->where('status_id', 1)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get(),
        ];
    }

    /**
     * Get override history for super admin with complex OR conditions
     */
    private function getOverrideHistorySuperAdmin(): Collection
    {
        return UserOverrideHistory::select('id', 'user_id', 'old_direct_overrides_amount', 'old_indirect_overrides_amount', 'old_office_overrides_amount', 'old_office_stack_overrides_amount')
            ->where('action_item_status', 0)
            ->where(function ($query) {
                $query->where('old_direct_overrides_amount', '!=', 0)
                    ->orWhere('old_direct_overrides_type', '!=', '')
                    ->orWhere('old_indirect_overrides_amount', '!=', 0)
                    ->orWhere('old_indirect_overrides_type', '!=', '')
                    ->orWhere('old_office_overrides_amount', '!=', 0)
                    ->orWhere('old_office_overrides_type', '!=', '')
                    ->orWhere('old_office_stack_overrides_amount', '!=', 0);
            })
            ->orderBy('id', 'DESC')
            ->limit(3)
            ->get();
    }

    /**
     * Get redline history for super admin with complex OR conditions
     */
    private function getRedlineHistorySuperAdmin(): Collection
    {
        return UserRedlines::select('id', 'user_id', 'redline_amount_type', 'old_redline')
            ->where('action_item_status', 0)
            ->where(function ($query) {
                $query->where('old_redline_amount_type', '!=', '')
                    ->orWhere('old_redline_type', '!=', '')
                    ->orWhere('old_redline', '!=', 0);
            })
            ->orderBy('id', 'DESC')
            ->limit(3)
            ->get();
    }

    /**
     * Format history data for API response
     */
    public function formatHistoryDataForResponse(array $historyData): array
    {
        $counts = [];
        $formattedData = [];

        // Format override data
        if ($historyData['override']->isNotEmpty()) {
            $counts['override'] = $historyData['override']->count();
            $formattedData['override_data'] = $historyData['override']->map(function ($override) {
                return [
                    'id' => $override->id,
                    'title' => 'Acknowledge Changes To Contract',
                    'type' => 'Override Changes',
                    'status' => 'Acknowledge',
                    'user_id' => $override->user_id,
                ];
            })->toArray();
        }

        // Format redline data
        if ($historyData['redline']->isNotEmpty()) {
            $counts['redline'] = $historyData['redline']->count();
            $formattedData['redline_data'] = $historyData['redline']->map(function ($redline) {
                $rtype = ($redline->redline_amount_type == 'Fixed') ? 'Fixed' : 'Location';

                return [
                    'id' => $redline->id,
                    'title' => 'Acknowledge Changes To Contract',
                    'type' => $rtype.' Redline Changes',
                    'status' => 'Acknowledge',
                    'user_id' => $redline->user_id,
                ];
            })->toArray();
        }

        // Format commission data
        if ($historyData['commission']->isNotEmpty()) {
            $counts['commission'] = $historyData['commission']->count();
            $formattedData['commission_data'] = $historyData['commission']->map(function ($commission) {
                $ctype = ($commission->self_gen_user == '1') ? 'Self Gen ' : '';

                return [
                    'id' => $commission->id,
                    'title' => 'Acknowledge Changes To Contract',
                    'type' => $ctype.'Commission Changes',
                    'status' => 'Acknowledge',
                    'user_id' => $commission->user_id,
                ];
            })->toArray();
        }

        // Format upfront data
        if ($historyData['upfront']->isNotEmpty()) {
            $counts['upfront'] = $historyData['upfront']->count();
            $formattedData['upfront_data'] = $historyData['upfront']->map(function ($upfront) {
                return [
                    'id' => $upfront->id,
                    'title' => 'Acknowledge Changes To Contract',
                    'type' => 'Upfront Changes',
                    'status' => 'Acknowledge',
                    'user_id' => $upfront->user_id,
                ];
            })->toArray();
        }

        // Format withheld data
        if ($historyData['withheld']->isNotEmpty()) {
            $counts['withheld'] = $historyData['withheld']->count();
            $formattedData['withheld_data'] = $historyData['withheld']->map(function ($withheld) {
                return [
                    'id' => $withheld->id,
                    'title' => 'Acknowledge Changes To Contract',
                    'type' => 'Withheld Changes',
                    'status' => 'Acknowledge',
                    'user_id' => $withheld->user_id,
                ];
            })->toArray();
        }

        // Format user organization data
        if ($historyData['userOrganization']->isNotEmpty()) {
            $counts['userOrganization'] = $historyData['userOrganization']->count();
            $formattedData['position_data'] = $historyData['userOrganization']->map(function ($userOrganization) {
                return [
                    'id' => $userOrganization->id,
                    'title' => 'Acknowledge Changes To Contract',
                    'type' => 'Position Changes',
                    'status' => 'Acknowledge',
                    'user_id' => $userOrganization->user_id,
                ];
            })->toArray();
        }

        // Format hiring data
        if ($historyData['hiringAccepted']->isNotEmpty()) {
            $counts['hiring'] = $historyData['hiringAccepted']->count();
            $formattedData['hiring_data'] = $historyData['hiringAccepted']->map(function ($hiring) {
                return [
                    'id' => $hiring->id,
                    'title' => 'Offer Letter has been Accepted',
                    'type' => 'Offer Letter Accepted',
                    'status' => 'Offer Letter Accepted',
                    'item_type' => 'hiring_document',
                    'user_id' => $hiring->id,
                ];
            })->toArray();
        }

        return [
            'counts' => $counts,
            'formatted_data' => $formattedData,
            'total_acknowledge_count' => array_sum($counts),
        ];
    }

    /**
     * Clear cache for specific user when data changes
     */
    public function clearUserCache(int $userId, bool $isSuperAdmin = false, bool $isManager = false): void
    {
        $cacheKey = $this->getCacheKey($userId, $isSuperAdmin, $isManager);
        Cache::forget($cacheKey);
    }

    /**
     * Clear all dashboard caches (for bulk updates)
     */
    public function clearAllDashboardCaches(): void
    {
        Cache::flush(); // Use with caution - only for maintenance
    }
}
